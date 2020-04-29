# Grav Webcomponents Plugin

Webcomponents is a [Grav](http://github.com/getgrav/grav) plugin that can be used to get webcomponents integrated into your Grav site with ease. Simply drop your webcomponents into user/webcomponents (unpacked from bower_components if using a framework like Polymer).

## Installation

### GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm).  From the root of your Grav install type:

    bin/gpm install webcomponents

### Manual Installation

If for some reason you can't use GPM you can manually install this plugin. Download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `webcomponents`.

You should now have all the plugin files under:

    `/your/site/grav/user/plugins/webcomponents`

## Usage

This should give you the dependencies you need to get going.
1. Enable the Web components plugin and any dependencies it requires.
2. The default is to serve JS assets up from a CDN.
   Should you need to change this keep reading into building your own assets.


# Front end Developers
You may build Web components from source if needed. We default to use CDNs which will effectively point to
this directory or some mutation of it -- https://github.com/elmsln/HAXcms/tree/master/build

If you want to build everything from source, your welcome to use yarn / npm to do so though our
build routine effectively will end in the same net result.  If you want to do custom build routines
such as rollup or webpack and not use our prebuilt copies / split build approaches, then your welcome
to check the box related to not loading front end assets in the settings page in order to tailor
the build to your specific needs.

## Getting dependencies
You need polymer cli (not polymer but the CLI library) in order to interface with web components in your site. Get polymer cli installed prior to usage of this (and (yarn)[https://yarnpkg.com/lang/en/docs/install/#mac-stable] / an npm client of some kind)
```bash
$ yarn global add polymer-cli
```
Perform this on your computer locally, this doesn't have to be installed on your server.

## Usage
- Find https://github.com/elmsln/unbundled-webcomponents and run the tooling to create your build (`yarn install` then `yarn run build`)
- create a `/your/site/grav/user/data/webcomponents` directory
- copy the files from https://github.com/elmsln/unbundled-webcomponents into `/your/site/grav/user/data/webcomponents`

### Shouldn't I put web components in my theme?
We don't think so. While it may seem counter intuitive, the theme layer should be effectively implementing what the site is saying is available. If you think of standard HTML tags are being part of this (p, div, a, etc) then it makes a bit more sense. You don't want functional HTML components to ONLY be supplied if your theme is there, you want your theme to implement and leverage the components.

## New to web components?
We built our own tooling to take the guess work out of creating, publishing and testing web components. We highly recommend you use this tooling though it's not required:
- https://open-wc.org - great, simple tooling and open community resource
- https://github.com/elmsln/wcfactory - Build your own web component library
- https://github.com/elmsln/lrnwebcomponents - Our invoking of this tooling to see what a filled out repo looks like