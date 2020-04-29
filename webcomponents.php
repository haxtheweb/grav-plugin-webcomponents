<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;
use \Grav\Common\Grav;
use \Grav\Common\Utils;
use \Grav\Common\Page\Page;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Yaml\Yaml;
use Grav\Plugin\AtoolsPlugin;

class WebcomponentsPlugin extends Plugin
{
  /**
   * @return array
   */
  public static function getSubscribedEvents()
  {
    return [
      'onThemeInitialized' => ['onThemeInitialized', 0],
    ];
  }

  /**
   * Initialize configuration
   */
  public function onThemeInitialized()
  {
    // enable our twig site vars which will apply the polyfills and built files to page
    $this->enable([
      'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
    ]);
  }

  /**
   * If enabled on this page, load the JS + CSS and set the selectors.
   */
  public function onTwigSiteVariables() {
    $config = $this->config->get('plugins.webcomponents');
    $assets = $this->grav['assets'];
    $base = $this->getBaseURL();
    $inline = '';
    $preconnect = array(
      'https://fonts.googleapis.com',
      'https://cdnjs.cloudflare.com',
      'https://i.creativecommons.org',
      'https://licensebuttons.net',
      $base
    );
    $loader = array(
      "preconnect" => $preconnect,
      "preload" => array(
        $base . "build.js",
        $base . "wc-registry.json",
        $base . "build/es6/node_modules/web-animations-js/web-animations-next-lite.min.js",
      ),
      "modulepreload" => array(
        $base . "build/es6/node_modules/@lrnwebcomponents/wc-autoload/wc-autoload.js",
        $base . "build/es6/node_modules/@lrnwebcomponents/dynamic-import-registry/dynamic-import-registry.js"
      )
    );
    foreach ($loader as $rel => $data) {
      foreach ($data as $key => $src) {
        $tag = array(
          '#tag' => 'link',
          '#attributes' => array(
            'rel' => $rel,
            'href' => $src,
          ),
        );
        if ($rel == "modulepreload") {
          $inline .= '<link rel="preload" as="script" crossorigin="anonymous" href="' . $src . '" />';
        }
        $inline .= '<link rel="' . $rel . '" href="' . $src . '"';
        if ($rel == "preload") {
          if ($src == $base . "wc-registry.json") {
            $inline .= ' as="fetch"';
            $inline .= ' crossorigin="anonymous"';
          }
          else {
            $inline .= ' as="script"';
          }
        }
        $inline .= ' />' . "\n";
      }
    }
    // hook into webomponents service to get our header material we need for the polyfill
    $inline .= $this->applyWebcomponents($base, $base);
    // add it into the document
    $assets->addInlineJs('</script>' . $inline . '<script>', array('priority' => 102));
  }
  /**
   * This applies all pieces of a standard build appended to the header
   */
  public function applyWebcomponents($directory = '/', $cdn = '/') {
    return $this->getBuild($directory, "false", $cdn);
  }
  /**
   * Front end logic for ES5-AMD, ES6-AMD, ES6 version to deliver
   */
  public function getBuild($directory  = '/', $forceUpgrade = "false", $cdn = '/') {
    return '
    <script>
      window.__appCDN="' . $cdn . '";
      window.__appForceUpgrade=' . $forceUpgrade . ';
    </script>
    <script src="' . $directory . 'build.js"></script>';
  }

  /**
   * Return the base url for forming paths on the front end.
   * @return string  The base url path to the user / data / webcomponents directory
   */
  public function getBaseURL() {
    if ($this->config->get('plugins.webcomponents.location') == 'user/data/webcomponents/') {
      return $this->grav['base_url'] . '/user/data/webcomponents/';
    }
    else if ($this->config->get('plugins.webcomponents.location') != 'other') {
      return $this->config->get('plugins.webcomponents.location');
    }
    else if ($this->config->get('plugins.webcomponents.location_other') != '') {
      return $this->config->get('plugins.webcomponents.location_other');
    }
    else {
      return $this->grav['base_url'] . '/user/data/webcomponents/';
    }
  }
}
