<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;
use \Grav\Common\Grav;
use \Grav\Common\Page\Page;

class WebcomponentsPlugin extends Plugin
{
  /**
  * @return array
  */
  public static function getSubscribedEvents()
  {
    return [
        'onThemeInitialized' => ['onThemeInitialized', 0]
    ];
  }

  /**
  * Initialize configuration
  */
  public function onThemeInitialized()
  {
    if ($this->isAdmin()) {
      return;
    }

    $load_events = false;

    // if not always_load see if the theme expects to load the webcomponents plugin
    if (!$this->config->get('plugins.webcomponents.always_load')) {
      $theme = $this->grav['theme'];
      if (isset($theme->load_webcomponents_plugin) && $theme->load_webcomponents_plugin) {
        $load_events = true;
      }
    }
    else {
      $load_events = true;
    }

    if ($load_events) {
      $this->enable([
        'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
      ]);
    }
  }

  /**
   * If enabled on this page, load the JS + CSS and set the selectors.
   */
  public function onTwigSiteVariables() {
    $config = $this->config->get('plugins.webcomponents');
    // discover and autoload our components
    $assets = $this->grav['assets'];
    // directory they live in physically
    $dir = getcwd() . '/user/webcomponents/';
    $base = $this->grav['base_url'] . '/user/webcomponents/';
    $polyfill = $base . 'webcomponents/webcomponentsjs/webcomponents-lite.min.js';
    // find all files
    $files = $this->findHTMLIncludes($dir, $base);
    // build the inline import
    $inline = "
// simple performance imporvements for Polymer
window.Polymer = {
  dom: 'shady',
  lazyRegister: true
};
window.onload = function() {
  if ('registerElement' in document
    && 'import' in document.createElement('link')
    && 'content' in document.createElement('template')) {
    // platform is good!
  }
  else {
    // polyfill the platform!
    var e = document.createElement('script');
    e.src = '$polyfill';
    document.head.appendChild(e);
  }
  ";
    // include our elements
    foreach ($files as $file) {
      $inline .= "
  var link = document.createElement('link');
  link.rel = 'import';
  link.href = '$file';
  document.head.appendChild(link);" . "\n";
    }
    // close the function
    $inline .="
  };";
    // add it into the document
    $assets->addInlineJs($inline);
  }

  /**
   * Sniff out html files in a directory
   * @param  string $dir a directory to search for .html includes
   * @return array       an array of html files to look for web components in
   */
  public function findHTMLIncludes($dir, $base = 'user://', $ignore = array(), $find = '.html') {
    $files = array();
    // common things to ignore
    $ignore[] = '.';
    $ignore[] = '..';
    $ignore[] = 'index.html';
    if (is_dir($dir)) {
      // step into the polymer directory and find all html templates
      $di = new \DirectoryIterator($dir);
      foreach ($di as $fileinfo) {
        $fname = $fileinfo->getFilename();
        // check for our find value skipping ignored values
        if (strpos($fname, $find) && !in_array($fname, $ignore)) {
          $files[] = $base . $fileinfo->getFilename();
        }
        elseif (is_dir($dir . $fname) && !in_array($fname, $ignore)) {
          $di2 = new \DirectoryIterator($dir . $fname);
          foreach ($di2 as $fileinfo2) {
            $fname2 = $fileinfo2->getFilename();
            // check for our find value skipping ignored values
            if (strpos($fname2, $find) && !in_array($fname2, $ignore)) {
              $files[] = $base . $fname . '/' . $fname2;
            }
            elseif (is_dir($dir . $fname . '/' . $fname2) && !in_array($fname2, $ignore)) {
              $di3 = new \DirectoryIterator($dir . $fname . '/' . $fname2);
              foreach ($di3 as $fileinfo3) {
                $fname3 = $fileinfo3->getFilename();
                // check for our find value skipping ignored values
                if (strpos($fname3, $find) && !in_array($fname3, $ignore)) {
                  $files[] = $base . $fname . '/' . $fname2 . '/' . $fname3;
                }
              }
            }
          }
        }
      }
    }
    return $files;
  }
}
