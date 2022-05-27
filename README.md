# Local Google Fonts

Contributors: everpress  
Tags: googlefonts, google, fonts, gdpr, lgf, font, speed
Requires at least: 4.2  
Tested up to: 5.9  
Stable tag: 0.4
Requires PHP: 5.6+  
License: GPLv2 or later
Author: EverPress
Author URI: https://everpress.co

## Description

Host your used Google fonts on your server and make your site more GDPR compliant.

About 50 mio[\*](https://trends.builtwith.com/websitelist/Google-Font-API) sites use Google Fonts and in January 2022 a German court has ruled that using Google Fonts is a violation of Europe’s GDPR (General Data Protection Regulation).

more on [wptavern.com](https://wptavern.com/german-court-fines-website-owner-for-violating-the-gdpr-by-using-google-hosted-fonts)

## Screenshots

### 1. Option Interface.

![Option Interface.](.wordpress-org/screenshot-1.png)

### Features

### 1. Quick install (activate, setup and forget)

![Quick install (activate, setup and forget)](https://ps.w.org/local-google-fonts/assets/screenshot-1.png)

### 2. Automatically loads all used fonts to your server (wp-content/uploads)

### 3. Cleanup on plugin deactivation

### 4. Cleanup on plugin switch

## Installation

1. Upload the entire `local-google-fonts` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings => Google Fonts and decide which fonts should get loaded locally

## Changelog

### 0.4

-   improved handling of fonts
-   only loading required font files
-   supports now different types of variant declarations
-   add variants to list only if available in the source
-   added some css to improve settings page
-   you may have to reload fonts so please check the settings page

### 0.3

-   add explanation info on settings page

### 0.2

-   show info when no font is found
-   better handling of translated strings

### 0.1

-   initial release
