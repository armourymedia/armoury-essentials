# Armoury Essentials

A lightweight, performance-focused WordPress plugin that provides essential optimizations including login branding, admin simplifications, privacy enhancements, cache management, and media embeds.

## Features

### Dynamic Login Branding
- Custom login page styling with automatic brand color detection
- Sources colors from theme settings, FSE global styles, or customizer
- Clean, modern login form design with accessibility support
- Responsive and high-contrast mode compatible

### Admin Simplifications
- Removes admin color scheme picker for cleaner user profiles
- Disables post-by-email configuration for improved security
- Adds excerpt support to pages
- Optimizes Action Scheduler retention (1 day)

### Privacy Enhancements
- Automatic YouTube nocookie domain for embedded videos
- SlimSEO LinkedIn tags support
- Privacy-first approach to all features

### Cache Management
- Seamless SpinupWP and Cloudflare cache synchronization
- Automatic Cloudflare purge when SpinupWP clears cache
- Cloudflare APO Support with granular URL cache purging
- Zero-configuration required after initial setup

### Click-to-Play Video Embeds
- Performance-focused lazy loading for videos
- Support for YouTube, Vimeo, Bunny Stream, and Cloudflare Stream
- Full accessibility with keyboard navigation and screen reader support
- Privacy-enhanced embedding (YouTube nocookie, Vimeo DNT)

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Modern browser with JavaScript enabled

## Installation

1. Download the plugin files to `/wp-content/plugins/armoury-essentials/`
2. Activate through the WordPress admin panel
3. That's it - most features work immediately with zero configuration

## Updates

- Implements the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library
- Checks the [GitHub repository](https://github.com/armourymedia/armoury-essentials) for the latest version

## Configuration

### Brand Colors (Automatic)

The plugin automatically detects your site's primary color from:
1. FSE theme global styles (theme.json)
2. Classic theme customizer settings
3. Falls back to a default green (#1a7e60)

To override with a specific color, add to `wp-config.php`:
```php
define( 'AE_BRAND_COLOR', '#your-hex-color' );
```

### Cache Synchronization

#### Basic Setup (Cloudflare Cache)

To enable basic Cloudflare cache synchronization with SpinupWP:

1. **Get your Cloudflare Zone ID:**
   - Log in to Cloudflare dashboard
   - Select your domain
   - Find "Zone ID" in the right sidebar
   - Copy the value

2. **Create a Cloudflare API Token:**
   - Go to My Profile → API Tokens
   - Click "Create Token"
   - Select "Create Custom Token"
   - Configure:
     - **Permissions:** Zone → Cache Purge → Purge
     - **Zone Resources:** Include → Specific zone → Your domain
   - Create and copy the token

3. **Add to wp-config.php:**
```php
// Armoury Essentials - Cache Configuration
define( 'ARMOURY_CF_ZONE_ID', 'your_cloudflare_zone_id' );
define( 'ARMOURY_CF_API_TOKEN', 'your_cloudflare_api_token' );
```

#### Cloudflare APO Support

For sites using Cloudflare APO (Automatic Platform Optimization), enable granular cache purging to maintain optimal performance:

```php
// Enable APO mode for granular cache purging
define( 'ARMOURY_CF_APO_ENABLED', true );
```

**What this does:**
- **Without APO mode:** Only full cache purges occur when using SpinupWP's "Purge All Caches"
- **With APO mode:** Individual URLs are purged when content updates, maintaining APO's edge cache for unchanged content

**When to enable APO mode:**
- Only enable this on sites actively using Cloudflare APO
- Sites using standard Cloudflare caching should leave this disabled
- APO mode reduces unnecessary full cache purges, improving global performance

## Usage

### Video Embeds

1. Add an image to any post or page
2. Link the image to a supported video URL
3. The plugin automatically adds a play button overlay
4. Clicking loads and plays the video

**Supported video URLs:**
- YouTube: `https://www.youtube.com/watch?v=VIDEO_ID`
- YouTube Shorts: `https://www.youtube.com/shorts/VIDEO_ID`
- YouTube Short URLs: `https://youtu.be/VIDEO_ID`
- Vimeo: `https://vimeo.com/VIDEO_ID`
- Bunny Stream (Legacy): `https://iframe.mediadelivery.net/play/ACCOUNT/VIDEO_ID`
- Bunny Stream (Beta): `https://player.mediadelivery.net/embed/ACCOUNT/VIDEO_ID`
- Cloudflare Stream: `https://customer.cloudflarestream.com/VIDEO_ID/watch`

## Developer Hooks

### Add Custom Video Providers

```php
add_filter('ae_video_providers', function($providers) {
    $providers['custom'] = array(
        'pattern' => 'example.com/video',
        'embed' => array('/video/', '/embed/'),
        'allowed_hosts' => array('example.com')
    );
    return $providers;
});
```

### Modify Brand Color

```php
add_filter('ae_brand_color', function($color) {
    return '#ff0000'; // Return your custom color
});
```

## Performance

- **Conditional asset loading:** CSS/JS only load when needed
- **Zero database queries:** All operations use existing data
- **Lightweight:** ~10KB total assets
- **Lazy loading:** Videos load only on demand
- **Cache-friendly:** Works with all caching plugins
- **APO-optimized:** Maintains edge cache for best performance

## Security

- **URL validation:** Only embeds videos from approved domains
- **Sandboxed iframes:** Restrictive permissions for embedded content
- **Escaped output:** All dynamic content properly sanitized
- **API token security:** Credentials stored in wp-config.php, not database

## Accessibility

- **WCAG 2.1 AA compliant**
- **Full keyboard navigation**
- **Screen reader optimized**
- **High contrast mode support**
- **Reduced motion support**
- **Focus management**

## Troubleshooting

### Login colors not showing
- Check if your theme has a primary color defined
- Verify no caching plugin is blocking CSS
- Add `AE_BRAND_COLOR` constant to wp-config.php

### Videos not transforming
- Ensure image is linked to a supported video platform
- Check JavaScript console for errors
- Verify video URL is publicly accessible

### Cache sync not working
- Confirm SpinupWP plugin is active
- Verify Cloudflare credentials in wp-config.php
- Check debug.log for error messages
- For APO sites, ensure `ARMOURY_CF_APO_ENABLED` is set to `true`

### APO cache not purging correctly
- Verify Cloudflare APO is active on your domain
- Check that `ARMOURY_CF_APO_ENABLED` is defined as `true`
- Ensure the Cloudflare plugin is enabled and configured
- Check debug.log for specific API error messages

## Support

For issues, questions, or feature requests, visit [Armoury Media](https://www.armourymedia.com/).

## License

GPL v3 or later

## Credits

Created by [Armoury Media](https://www.armourymedia.com/) - WordPress websites for solo professionals.

## Changelog

### 1.1.3
* Added: Support for Bunny Stream beta video player URLs (player.mediadelivery.net)
* Improved: Video embed detection now handles both legacy and beta Bunny Stream player formats

### 1.1.2
* Prevent ffmailpoet translation string notice from writing to debug.log

### 1.1.1
* Changed: Updated Plugin Update Checker library to v5.6 release

### 1.1.0
* Added: Cloudflare APO support with granular URL cache purging
* Added: `ARMOURY_CF_APO_ENABLED` constant for enabling APO mode
* Improved: Cache management efficiency for global content delivery
* Improved: URL deduplication to prevent redundant API calls

### 1.0.2
* Version bump to test and confirm update from GitHub repository functionality.

### 1.0.1
* Added: Plugin Update Checker library to enable plugin updates from the GitHub repository.

### 1.0.0
- Initial release
- Dynamic login branding with automatic color detection
- Admin simplifications and security enhancements
- Optional SpinupWP/Cloudflare cache synchronization
- Click-to-play video embeds with privacy features
- Full accessibility and performance optimization
- Input validation for all user-provided data
- Performance optimizations with caching and regex improvements
- Support for YouTube Shorts and enhanced time parameter handling
- Cloudflare Zone ID validation
- Action Scheduler conditional loading