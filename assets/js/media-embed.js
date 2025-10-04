/**
 * Armoury Essentials - Media Embed
 *
 * @package ArmouryEssentials
 * @since 1.0.0
 */

(function() {
	'use strict';

	const config = window.aeMediaConfig || {};
	const processed = new WeakSet();

	/**
	 * Initialize when DOM is ready.
	 */
	function init() {
		const selectors = buildSelectors();
		if (!selectors) return;

		processVideoLinks(selectors);

		// Watch for dynamically added content.
		if (window.MutationObserver) {
			const observer = new MutationObserver((mutations) => {
				const hasNewNodes = mutations.some(m => m.addedNodes.length > 0);
				if (hasNewNodes) {
					processVideoLinks(selectors);
				}
			});
			observer.observe(document.body, { 
				childList: true, 
				subtree: true 
			});
		}
	}

	/**
	 * Build CSS selectors from provider patterns.
	 */
	function buildSelectors() {
		if (!config.providers) return null;

		const selectors = [];
		Object.values(config.providers).forEach(provider => {
			provider.pattern.split('|').forEach(pattern => {
				selectors.push(`a[href*="${pattern}"]`);
			});
		});
		return selectors.join(', ');
	}

	/**
	 * Process all video links on the page.
	 */
	function processVideoLinks(selectors) {
		document.querySelectorAll(selectors).forEach(link => {
			if (isValidVideoLink(link)) {
				setupVideoLink(link);
			}
		});
	}

	/**
	 * Validate video link for security.
	 */
	function isValidVideoLink(link) {
		if (!link.href || processed.has(link)) return false;
		
		try {
			const url = new URL(link.href);
			
			// Find matching provider.
			for (const provider of Object.values(config.providers)) {
				if (!provider.allowed_hosts) continue;
				
				// Check if hostname is in allowed list.
				const isAllowed = provider.allowed_hosts.some(host => 
					url.hostname === host || url.hostname.endsWith('.' + host)
				);
				
				if (isAllowed) return true;
			}
		} catch (e) {
			return false;
		}
		
		return false;
	}

	/**
	 * Setup video link for click-to-play.
	 */
	function setupVideoLink(link) {
		const img = link.querySelector('img');
		if (!img) return;
		
		processed.add(link);

		// Add class and create play button.
		link.classList.add('ae-video-link');
		const playButton = document.createElement('div');
		playButton.className = 'ae-play-button';
		link.appendChild(playButton);

		// Setup accessibility.
		const i18n = config.i18n || {};
		let ariaLabel = i18n.playVideo || 'Play video';
		if (img.alt) {
			// Alt text is already escaped by the browser when using getAttribute
			ariaLabel += `: ${img.alt}`;
		}
		
		link.setAttribute('aria-label', ariaLabel);
		link.setAttribute('role', 'button');
		link.setAttribute('tabindex', '0');

		// Event handlers.
		link.addEventListener('click', handleVideoClick);
		link.addEventListener('keydown', handleVideoKeydown);
	}

	/**
	 * Handle video link click.
	 */
	function handleVideoClick(e) {
		e.preventDefault();
		embedVideo(this);
	}

	/**
	 * Handle keyboard interaction.
	 */
	function handleVideoKeydown(e) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			embedVideo(this);
		}
	}

	/**
	 * Embed video in place of link.
	 */
	function embedVideo(link) {
		try {
			const embedUrl = getEmbedUrl(link.href);
			if (!embedUrl) {
				console.warn('AE: Could not generate embed URL for', link.href);
				return;
			}

			// Create wrapper with loading state.
			const wrapper = document.createElement('div');
			wrapper.className = 'ae-video-wrapper ae-loading';

			// Create loader.
			const loader = document.createElement('div');
			loader.className = 'ae-loader';
			loader.setAttribute('aria-label', 'Loading video');
			wrapper.appendChild(loader);

			// Create iframe.
			const iframe = document.createElement('iframe');
			iframe.src = embedUrl;
			
			// Use minimal, compatible permissions that work across browsers
			// Only set the permissions actually needed for video playback
			iframe.allow = 'autoplay';
			
			// Add standard attributes for video embeds
			iframe.setAttribute('allowfullscreen', '');
			iframe.setAttribute('frameborder', '0');
			
			// Title for accessibility
			iframe.title = config.i18n?.videoPlayer || 'Video player';
			
			// Note: We intentionally don't use sandbox attribute for video embeds
			// as it causes more problems than it solves with trusted providers

			// Handle iframe load success.
			iframe.onload = () => {
				wrapper.classList.remove('ae-loading');
				loader.remove();
				announceToScreenReader(config.i18n?.videoPlayer || 'Video player loaded');
			};

			// Handle iframe load error.
			iframe.onerror = () => {
				wrapper.classList.remove('ae-loading');
				wrapper.classList.add('ae-error');
				loader.textContent = config.i18n?.loadError || 'Video could not be loaded';
				loader.setAttribute('role', 'alert');
			};

			// Add iframe and replace link.
			wrapper.appendChild(iframe);
			link.parentNode.replaceChild(wrapper, link);
			
			// Focus management for accessibility.
			iframe.focus();
			
		} catch (error) {
			console.error('AE: Error embedding video', error);
		}
	}

	/**
	 * Convert video URL to embed URL.
	 */
	function getEmbedUrl(url) {
		if (!config.providers) return null;

		try {
			const urlObj = new URL(url);
			
			for (const [key, provider] of Object.entries(config.providers)) {
				const patterns = provider.pattern.split('|');
				
				for (const pattern of patterns) {
					if (url.includes(pattern)) {
						// Validate against allowed hosts.
						if (provider.allowed_hosts) {
							const isAllowed = provider.allowed_hosts.some(host => 
								urlObj.hostname === host || urlObj.hostname.endsWith('.' + host)
							);
							if (!isAllowed) continue;
						}
						
						// Handle different provider types.
						if (provider.embed === 'youtube') {
							return getYouTubeEmbedUrl(url);
						} else if (provider.embed === 'vimeo') {
							return getVimeoEmbedUrl(url);
						} else if (Array.isArray(provider.embed)) {
							// Simple replacement.
							return url.replace(provider.embed[0], provider.embed[1]);
						}
					}
				}
			}
		} catch (e) {
			console.error('AE: Invalid URL', e);
		}
		
		return null;
	}

	/**
	 * Get YouTube embed URL.
	 */
	function getYouTubeEmbedUrl(url) {
		const urlObj = new URL(url);
		let videoId = null;

		// Extract video ID from different URL formats.
		if (urlObj.hostname.includes('youtube.com')) {
			// Standard watch URL.
			videoId = urlObj.searchParams.get('v');
			
			// Embed URL.
			if (!videoId && urlObj.pathname.includes('/embed/')) {
				videoId = urlObj.pathname.split('/embed/')[1].split('/')[0].split('?')[0];
			}
			
			// Shorts URL.
			if (!videoId && urlObj.pathname.includes('/shorts/')) {
				videoId = urlObj.pathname.split('/shorts/')[1].split('/')[0].split('?')[0];
			}
		} else if (urlObj.hostname === 'youtu.be') {
			// Short URL format.
			videoId = urlObj.pathname.slice(1).split('/')[0].split('?')[0];
		}

		// Handle empty or invalid video ID
		if (!videoId || videoId.length < 1) {
			console.warn('AE: Invalid YouTube video ID');
			return null;
		}

		// Use nocookie domain for privacy.
		const params = new URLSearchParams({
			autoplay: '1',
			rel: '0',
			modestbranding: '1'
		});
		
		// Preserve time parameter if present.
		const timeParam = urlObj.searchParams.get('t') || urlObj.searchParams.get('start');
		if (timeParam) {
			// Convert time to seconds if needed.
			let seconds = timeParam;
			if (isNaN(timeParam)) {
				// Handle format like "2m30s" or "1h30m45s".
				const match = timeParam.match(/(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/);
				if (match) {
					seconds = (parseInt(match[1] || 0) * 3600) + 
							 (parseInt(match[2] || 0) * 60) + 
							 parseInt(match[3] || 0);
				}
			}
			if (seconds) {
				params.set('start', seconds);
			}
		}
		
		return `https://www.youtube-nocookie.com/embed/${videoId}?${params.toString()}`;
	}

	/**
	 * Get Vimeo embed URL.
	 */
	function getVimeoEmbedUrl(url) {
		const urlObj = new URL(url);
		const pathParts = urlObj.pathname.split('/').filter(Boolean);
		
		// Find the video ID (usually the last numeric part).
		let videoId = null;
		for (let i = pathParts.length - 1; i >= 0; i--) {
			if (/^\d+$/.test(pathParts[i])) {
				videoId = pathParts[i];
				break;
			}
		}
		
		// Handle empty or invalid video ID
		if (!videoId || videoId.length < 1) {
			console.warn('AE: Invalid Vimeo video ID');
			return null;
		}
		
		const params = new URLSearchParams({
			autoplay: '1',
			dnt: '1', // Do not track for privacy.
			title: '0',
			byline: '0',
			portrait: '0'
		});
		
		return `https://player.vimeo.com/video/${videoId}?${params.toString()}`;
	}

	/**
	 * Announce message to screen readers.
	 */
	function announceToScreenReader(message) {
		const announcement = document.createElement('div');
		announcement.setAttribute('aria-live', 'polite');
		announcement.setAttribute('aria-atomic', 'true');
		announcement.className = 'screen-reader-text';
		announcement.style.position = 'absolute';
		announcement.style.left = '-9999px';
		announcement.textContent = message;
		
		document.body.appendChild(announcement);
		setTimeout(() => announcement.remove(), 1000);
	}

	// Initialize based on DOM state.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();