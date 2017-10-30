<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;
use \Grav\Common\Grav;
use \Grav\Common\Utils;
use \Grav\Common\Page\Page;

define('WEBCOMPONENTS_CLASS_IDENTIFIER', 'webcomponent-plugin-selector');
define('WEBCOMPONENTS_APP_PATH', 'apps');

class WebcomponentsPlugin extends Plugin
{
  public $activeApp;
  /**
   * @return array
   */
  public static function getSubscribedEvents()
  {
    return [
      'onThemeInitialized' => ['onThemeInitialized', 0],
      'onPluginsInitialized' => ['onPluginsInitialized', 100000]
    ];
  }

  /**
   * Initialize the plugin
   */
  public function onPluginsInitialized()
  {
      // Don't proceed if we are in the admin plugin
      if ($this->isAdmin()) {
        return;
      }

      $uri = $this->grav['uri'];
      // load autoloaded paths from manifest files in our apps
      $routes = $this->loadWebcomponentApps();
      foreach ($routes as $machine_name => $app) {
        // if our route matches one we have, load up
        if ("/apps/$machine_name" == $uri->path()) {
          $this->activeApp = (array)$app;
          $this->enable([
              'onPageInitialized' => ['onPageInitialized', 100000]
          ]);
        }
      }
  }

  /**
   * Autoload a webcomponent app.
   */
  public function onPageInitialized()
  {
    // @todo more here as this is 404'ing.
    $output = $this->renderApp($this->activeApp);
    // what I think should work
    $this->grav['page']->content($output);
    // @not working, not what I want to do, but gets it on the page
    $assets = $this->grav['assets'];
    $assets->addInlineJs('</script>' . $output . '<script>', array('priority' => 102, 'group' => 'head'));
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
    $dir = $this->webcomponentsDir();
    $polyfill = $this->getBaseURL() . 'webcomponents/webcomponentsjs/webcomponents-lite.min.js';
    // find all files
    $files = $this->findWebcomponentFiles($dir, $this->getBaseURL());
    $imports = '';
    // include our elements
    foreach ($files as $file) {
      $imports .= $this->createHTMLImport($file) . "\n";
    }
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
};
</script>" . $imports . "<script>";
    // add it into the document
    $assets->addInlineJs($inline, array('priority' => 102, 'group' => 'head'));
  }

  /**
   * Return the base url for forming paths on the front end.
   * @return string  The base path to the user / webcomponents directory
   */
  public function getBaseURL() {
    return $this->grav['base_url'] . '/user/webcomponents/';
  }

  /**
   * Return the file system directory for forming paths on the front end.
   * @return string  The base path to the user / webcomponents directory
   */
  public function webcomponentsDir() {
    return getcwd() . '/user/webcomponents/';
  }
  /**
   * Simple HTML Import render.
   */
  public function createHTMLImport($path, $rel = 'import') {
    return '<link rel="' . $rel . '" href="' . $path . '">';
  }

  /**
   * Sniff out html files in a directory
   * @param  string $dir a directory to search for .html includes
   * @return array       an array of html files to look for web components in
   */
  public function findWebcomponentFiles($dir, $base, $ignore = array(), $find = '.html') {
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

  /**
   * Load all apps where we find a manifest.json file
   */
  public function discoverWebcomponentApps() {
    $return = array();
    $dir = $this->webcomponentsDir() . 'polymer/apps/';
    $files = $this->findWebcomponentFiles($dir, $this->getBaseURL() . 'polymer/apps/', array(), '.json');
    // walk the files
    foreach ($files as $file) {
      // read in the manifest file
      if (strpos($file, 'manifest.json')) {
        // load the manifest json file
        $tmp = str_replace($this->grav['base_url'], '', $file);
        $tmp = getcwd() . $tmp;
        $manifest = json_decode(file_get_contents($tmp));
        $manifest = (array)$manifest;
        $return[$manifest['name']] = array(
          'title' => $manifest['short_name'],
          'description' => $manifest['description'],
          'path' => str_replace('manifest.json', '', $file),
          'filepath' => str_replace('manifest.json', '', $tmp),
        );
        $return[$manifest['name']]['machine_name'] = $manifest['name'];
        // support for specific properties to be set in manifest
        if (isset($manifest['app_integration'])) {
          $app = (array)$manifest['app_integration'];
          // support for more expressive title specific to integrations
          if (isset($app['title'])) {
            $return[$manifest['name']]['title'] = $app['title'];
          }
          // support for opa-root integrations
          if (isset($app['opa-root'])) {
            $return[$manifest['name']]['opa-root'] = $app['opa-root'];
          }
          // support for generating a visualized menu item in the system
          if (isset($app['menu'])) {
            $return[$manifest['name']]['menu'] = $app['menu'];
          }
          // support for additional properties
          if (isset($app['properties'])) {
            $return[$manifest['name']]['properties'] = $app['properties'];
          }
          // support for additional slots
          if (isset($app['slots'])) {
            $return[$manifest['name']]['slots'] = $app['slots'];
          }
          // support for a endpoint paths for getting data into the app
          if (isset($app['endpoints'])) {
            $return[$manifest['name']]['endpoints'] = $app['endpoints'];
          }
          // support for discovering and autoloading an element-name.php file
          // to make decoupled development even easier!
          if (file_exists(str_replace('manifest.json', $manifest['name'] . '.php', $file))) {
            $return[$manifest['name']]['autoload'] = TRUE;
          }
          // support automatically making a block for this element
          if (isset($app['block'])) {
            $return[$manifest['name']]['block'] = $app['block'];
          }
          // general support for anything you want to store for context
          if (isset($app['context'])) {
            $return[$manifest['name']]['context'] = $app['context'];
          }
        }
      }
    }
    return $return;
  }

  /**
   * Load an app based on machine name
   */
  public function loadWebcomponentApps($machine_name = NULL, $force_rebuild = FALSE) {
    // load all app definitions
    $apps = $this->discoverWebcomponentApps();
    if (!is_null($machine_name)) {
      // validate that this bucket exists
      if (isset($apps[$machine_name])) {
        // check for autoloading flag if so then load the file which should contain
        // the functions needed to make the call happen
        if (isset($apps[$machine_name]['autoload']) && $apps[$machine_name]['autoload'] === TRUE) {
          include_once $apps[$machine_name]['path'] . $machine_name . '.php';
        }
        $apps[$machine_name]['machine_name'] = $machine_name;
        return $apps[$machine_name];
      }
      // nothing at this point, return nothing since we don't know that machine name
      return array();
    }
    // validate apps were found
    if (!empty($apps)) {
      return $apps;
    }
    // nothing at this point, return nothing
    return array();
  }

  /**
   * Render an app based on machine name.
   */
  public function renderApp($app = array()) {
    $return = '';
    $vars = array();
    $machine_name = $app['machine_name'];
    $assets = $this->grav['assets'];
    // set a custom is_app property so other render alters can realize
    // this is an app rendering being modified and not a normal page component
    $app['is_app'] = TRUE;
    // ensure this exists
    if (!empty($machine_name) && !empty($app)) {
      $hash = filesize($app['filepath'] . 'manifest.json');
      $inline = '</script>' . $this->createHTMLImport($app['path'] . 'manifest.json?h' . $hash, 'manifest') . '<script>';
      $assets->addInlineJs($inline, array('priority' => 102, 'group' => 'head'));

      $hash = filesize($app['filepath'] . 'src/' . $machine_name . '/' . $machine_name . '.html');
      $inline = '</script>' . $this->createHTMLImport($app['path'] . 'src/' . $machine_name . '/' . $machine_name . '.html?h' . $hash, 'manifest') . '<script>';
      $assets->addInlineJs($inline, array('priority' => 102, 'group' => 'head'));
      // construct the tag base to be written
      $vars = array(
        'tag' => $machine_name,
        'properties' => array(),
      );
      // support for properties to be mixed in automatically
      if (isset($app->properties)) {
        foreach ($app->properties as $key => $property) {
          // support for simple function based callbacks for properties from functions
          if (is_array($property) && isset($property['callback'])) {
            // ensure it exists of that would be bad news bears
            if (function_exists($property['callback'])) {
              // only allow for simple function callbacks
              $vars['properties'][$key] = call_user_func($property['callback']);
            }
            else {
              // well it failed but at least set it to nothing
              $vars['properties'][$key] = NULL;
              // missing function, let's log this to the screen or watchdog if its debug mode
              $this->grav['debugger']->addMessage("The $machine_name app wants to hit the callback " . $property['callback'] . " for property $key but this function could not be found");
            }
          }
          else {
            $vars['properties'][$key] = $property;
          }
        }
      }
      // support for slots to be mixed in automatically
      if (isset($app->slots)) {
        foreach ($app->slots as $key => $slot) {
          // support for simple function based callbacks for slots from functions
          if (is_array($slot) && isset($slot['callback'])) {
            // ensure it exists of that would be bad news bears
            if (function_exists($slot['callback'])) {
              // only allow for simple function callbacks
              $vars['slots'][$key] = call_user_func($slot['callback']);
            }
            else {
              // well it failed but at least set it to nothing
              $vars['slots'][$key] = NULL;
              // missing function, let's log this to the screen or watchdog if its debug mode
              $this->grav['debugger']->addMessage("The $machine_name app wants to hit the callback " . $slot['callback'] . " for slot $key but this function could not be found");
            }
          }
          else {
            $vars['slots'][$key] = $slot;
          }
        }
      }
      // special properties that register endpoints
      if (isset($app->endpoints)) {
        // all end points will be able to use this for simple, secure construction
        // @see secure-request webcomponent for behavior details if doing app development
        $vars['properties']['csrf-token'] = Utils::getNonce('webcomponentapp');
        $vars['properties']['end-point'] = $this->getBaseURL() . WEBCOMPONENTS_APP_PATH . '/' . $machine_name;
        $vars['properties']['base-path'] = $this->getBaseURL() . WEBCOMPONENTS_APP_PATH . '/';
        // see if anything needs ripped into the element
        foreach ($app->endpoints as $endpointpath => $endpoint) {
          if (isset($endpoint->property)) {
            $vars['properties'][$endpoint->property] = $vars['properties']['end-point'] . '/' . $endpointpath . '?token=' . $vars['properties']['csrf-token'];
          }
        }
      }
      // support for one page apps to pass down their root correctly
      else if (isset($app['opa-root'])) {
        $vars['properties']['csrf-token'] = Utils::getNonce('webcomponentapp');
        $vars['properties']['base-path'] = $this->getBaseURL() . WEBCOMPONENTS_APP_PATH . '/';
      }
      // support compressing slots into the innerHTML tag
      if (isset($vars['slots'])) {
        // support single slot name
        if (is_string($vars['slots'])) {
          $vars['innerHTML'] = $vars['slots'];
        }
        // support for multiple slot names
        else if (is_array($vars['slots'])) {
          $vars['innerHTML'] = '';
          foreach ($vars['slots'] as $name => $content) {
            $vars['innerHTML'] .= '<span slot="' . $name . '">' . $content . '</span>';
          }
        }
      }
      else {
        $vars['innerHTML'] = '';
      }
      // add on custom class to help idenfity we delivered this
      if (!isset($vars['properties']['class'])) {
        $vars['properties']['class'] = WEBCOMPONENTS_CLASS_IDENTIFIER;
      }
      else {
        $vars['properties']['class'] .= ' ' . WEBCOMPONENTS_CLASS_IDENTIFIER;
      }

      $return = $this->renderWebcomponent($vars);
    }
    return $return;
  }
  /**
   * Render a webcomponent to the screen.
   * @param  [type] $vars [description]
   * @return [type]       [description]
   */
  public function renderWebcomponent($vars) {
    return '<' . $vars['tag'] . ' ' . $this->webcomponentAttributes($vars['properties']) . '>' . "\n" . $vars['innerHTML'] . "\n" . '</' . $vars['tag'] . '>' . "\n";
  }
  /**
   * Convert array into attributes for placement in an HTML tag.
   * @param  array  $attributes array of attribute name => value pairs
   * @return string             HTML name="value" output
   */
  protected function webcomponentAttributes(array $attributes = array()) {
    foreach ($attributes as $attribute => &$data) {
      $data = implode(' ', (array) $data);
      $data = $attribute . '="' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $attributes ? ' ' . implode(' ', $attributes) : '';
  }
}
