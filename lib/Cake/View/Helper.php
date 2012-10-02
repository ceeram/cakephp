<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.View
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Router', 'Routing');
App::uses('Hash', 'Utility');

/**
 * Abstract base class for all other Helpers in CakePHP.
 * Provides common methods and features.
 *
 * @package       Cake.View
 */
class Helper extends Object {

/**
 * List of helpers used by this helper
 *
 * @var array
 */
	public $helpers = array();

/**
 * A helper lookup table used to lazy load helper objects.
 *
 * @var array
 */
	protected $_helperMap = array();

/**
 * The current theme name if any.
 *
 * @var string
 */
	public $theme = null;

/**
 * Request object
 *
 * @var CakeRequest
 */
	public $request = null;

/**
 * Plugin path
 *
 * @var string
 */
	public $plugin = null;

/**
 * Holds tag templates.
 *
 * @var array
 */
	public $tags = array();

/**
 * Holds the content to be cleaned.
 *
 * @var mixed
 */
	protected $_tainted = null;

/**
 * Holds the cleaned content.
 *
 * @var mixed
 */
	protected $_cleaned = null;

/**
 * The View instance this helper is attached to
 *
 * @var View
 */
	protected $_View;

/**
 * Minimized attributes
 *
 * @var array
 */
	protected $_minimizedAttributes = array(
		'compact', 'checked', 'declare', 'readonly', 'disabled', 'selected',
		'defer', 'ismap', 'nohref', 'noshade', 'nowrap', 'multiple', 'noresize',
		'autoplay', 'controls', 'loop', 'muted'
	);

/**
 * Format to attribute
 *
 * @var string
 */
	protected $_attributeFormat = '%s="%s"';

/**
 * Format to attribute
 *
 * @var string
 */
	protected $_minimizedAttributeFormat = '%s="%s"';

/**
 * Default Constructor
 *
 * @param View $View The View this helper is being attached to.
 * @param array $settings Configuration settings for the helper.
 */
	public function __construct(View $View, $settings = array()) {
		$this->_View = $View;
		$this->request = $View->request;
		if (!empty($this->helpers)) {
			$this->_helperMap = ObjectCollection::normalizeObjectArray($this->helpers);
		}
	}

/**
 * Provide non fatal errors on missing method calls.
 *
 * @param string $method Method to invoke
 * @param array $params Array of params for the method.
 * @return void
 */
	public function __call($method, $params) {
		trigger_error(__d('cake_dev', 'Method %1$s::%2$s does not exist', get_class($this), $method), E_USER_WARNING);
	}

/**
 * Lazy loads helpers. Provides access to deprecated request properties as well.
 *
 * @param string $name Name of the property being accessed.
 * @return mixed Helper or property found at $name
 */
	public function __get($name) {
		if (isset($this->_helperMap[$name]) && !isset($this->{$name})) {
			$settings = array_merge((array)$this->_helperMap[$name]['settings'], array('enabled' => false));
			$this->{$name} = $this->_View->loadHelper($this->_helperMap[$name]['class'], $settings);
		}
		if (isset($this->{$name})) {
			return $this->{$name};
		}
		switch ($name) {
			case 'base':
			case 'here':
			case 'webroot':
			case 'data':
				return $this->request->{$name};
			case 'action':
				return isset($this->request->params['action']) ? $this->request->params['action'] : '';
			case 'params':
				return $this->request;
		}
	}

/**
 * Provides backwards compatibility access for setting values to the request object.
 *
 * @param string $name Name of the property being accessed.
 * @param mixed $value
 * @return mixed Return the $value
 */
	public function __set($name, $value) {
		switch ($name) {
			case 'base':
			case 'here':
			case 'webroot':
			case 'data':
				return $this->request->{$name} = $value;
			case 'action':
				return $this->request->params['action'] = $value;
		}
		return $this->{$name} = $value;
	}

/**
 * Finds URL for specified action.
 *
 * Returns a URL pointing at the provided parameters.
 *
 * @param string|array $url Either a relative string url like `/products/view/23` or
 *    an array of url parameters.  Using an array for urls will allow you to leverage
 *    the reverse routing features of CakePHP.
 * @param boolean $full If true, the full base URL will be prepended to the result
 * @return string  Full translated URL with base path.
 * @link http://book.cakephp.org/2.0/en/views/helpers.html
 */
	public function url($url = null, $full = false) {
		return h(Router::url($url, $full));
	}

/**
 * Checks if a file exists when theme is used, if no file is found default location is returned
 *
 * @param string $file The file to create a webroot path to.
 * @return string Web accessible path to file.
 */
	public function webroot($file) {
		$asset = explode('?', $file);
		$asset[1] = isset($asset[1]) ? '?' . $asset[1] : null;
		$webPath = "{$this->request->webroot}" . $asset[0];
		$file = $asset[0];

		if (!empty($this->theme)) {
			$file = trim($file, '/');
			$theme = $this->theme . '/';

			if (DS === '\\') {
				$file = str_replace('/', '\\', $file);
			}

			if (file_exists(Configure::read('App.www_root') . 'theme' . DS . $this->theme . DS . $file)) {
				$webPath = "{$this->request->webroot}theme/" . $theme . $asset[0];
			} else {
				$themePath = App::themePath($this->theme);
				$path = $themePath . 'webroot' . DS . $file;
				if (file_exists($path)) {
					$webPath = "{$this->request->webroot}theme/" . $theme . $asset[0];
				}
			}
		}
		if (strpos($webPath, '//') !== false) {
			return str_replace('//', '/', $webPath . $asset[1]);
		}
		return $webPath . $asset[1];
	}

/**
 * Generate url for given asset file. Depending on options passed provides full url with domain name.
 * Also calls Helper::assetTimestamp() to add timestamp to local files
 *
 * @param string|array Path string or url array
 * @param array $options Options array. Possible keys:
 *   `fullBase` Return full url with domain name
 *   `pathPrefix` Path prefix for relative urls
 *   `ext` Asset extension to append
 *   `plugin` False value will prevent parsing path as a plugin
 * @return string Generated url
 */
	public function assetUrl($path, $options = array()) {
		if (is_array($path)) {
			$path = $this->url($path, !empty($options['fullBase']));
		} elseif (strpos($path, '://') === false) {
			if (!array_key_exists('plugin', $options) || $options['plugin'] !== false) {
				list($plugin, $path) = $this->_View->pluginSplit($path, false);
			}
			if (!empty($options['pathPrefix']) && $path[0] !== '/') {
				$path = $options['pathPrefix'] . $path;
			}
			if (
				!empty($options['ext']) &&
				strpos($path, '?') === false &&
				substr($path, -strlen($options['ext'])) !== $options['ext']
			) {
				$path .= $options['ext'];
			}
			if (isset($plugin)) {
				$path = Inflector::underscore($plugin) . '/' . $path;
			}
			$path = h($this->assetTimestamp($this->webroot($path)));

			if (!empty($options['fullBase'])) {
				$base = $this->url('/', true);
				$len = strlen($this->request->webroot);
				if ($len) {
					$base = substr($base, 0, -$len);
				}
				$path = $base . $path;
			}
		}

		return $path;
	}

/**
 * Adds a timestamp to a file based resource based on the value of `Asset.timestamp` in
 * Configure.  If Asset.timestamp is true and debug > 0, or Asset.timestamp == 'force'
 * a timestamp will be added.
 *
 * @param string $path The file path to timestamp, the path must be inside WWW_ROOT
 * @return string Path with a timestamp added, or not.
 */
	public function assetTimestamp($path) {
		$stamp = Configure::read('Asset.timestamp');
		$timestampEnabled = $stamp === 'force' || ($stamp === true && Configure::read('debug') > 0);
		if ($timestampEnabled && strpos($path, '?') === false) {
			$filepath = preg_replace('/^' . preg_quote($this->request->webroot, '/') . '/', '', $path);
			$webrootPath = WWW_ROOT . str_replace('/', DS, $filepath);
			if (file_exists($webrootPath)) {
				return $path . '?' . @filemtime($webrootPath);
			}
			$segments = explode('/', ltrim($filepath, '/'));
			if ($segments[0] === 'theme') {
				$theme = $segments[1];
				unset($segments[0], $segments[1]);
				$themePath = App::themePath($theme) . 'webroot' . DS . implode(DS, $segments);
				return $path . '?' . @filemtime($themePath);
			} else {
				$plugin = Inflector::camelize($segments[0]);
				if (CakePlugin::loaded($plugin)) {
					unset($segments[0]);
					$pluginPath = CakePlugin::path($plugin) . 'webroot' . DS . implode(DS, $segments);
					return $path . '?' . @filemtime($pluginPath);
				}
			}
		}
		return $path;
	}

/**
 * Used to remove harmful tags from content.  Removes a number of well known XSS attacks
 * from content.  However, is not guaranteed to remove all possibilities.  Escaping
 * content is the best way to prevent all possible attacks.
 *
 * @param string|array $output Either an array of strings to clean or a single string to clean.
 * @return string|array cleaned content for output
 */
	public function clean($output) {
		$this->_reset();
		if (empty($output)) {
			return null;
		}
		if (is_array($output)) {
			foreach ($output as $key => $value) {
				$return[$key] = $this->clean($value);
			}
			return $return;
		}
		$this->_tainted = $output;
		$this->_clean();
		return $this->_cleaned;
	}

/**
 * Returns a space-delimited string with items of the $options array. If a
 * key of $options array happens to be one of:
 *
 * - 'compact'
 * - 'checked'
 * - 'declare'
 * - 'readonly'
 * - 'disabled'
 * - 'selected'
 * - 'defer'
 * - 'ismap'
 * - 'nohref'
 * - 'noshade'
 * - 'nowrap'
 * - 'multiple'
 * - 'noresize'
 *
 * And its value is one of:
 *
 * - '1' (string)
 * - 1 (integer)
 * - true (boolean)
 * - 'true' (string)
 *
 * Then the value will be reset to be identical with key's name.
 * If the value is not one of these 3, the parameter is not output.
 *
 * 'escape' is a special option in that it controls the conversion of
 *  attributes to their html-entity encoded equivalents.  Set to false to disable html-encoding.
 *
 * If value for any option key is set to `null` or `false`, that option will be excluded from output.
 *
 * @param array $options Array of options.
 * @param array $exclude Array of options to be excluded, the options here will not be part of the return.
 * @param string $insertBefore String to be inserted before options.
 * @param string $insertAfter String to be inserted after options.
 * @return string Composed attributes.
 * @deprecated This method will be moved to HtmlHelper in 3.0
 */
	protected function _parseAttributes($options, $exclude = null, $insertBefore = ' ', $insertAfter = null) {
		if (!is_string($options)) {
			$options = (array)$options + array('escape' => true);

			if (!is_array($exclude)) {
				$exclude = array();
			}

			$exclude = array('escape' => true) + array_flip($exclude);
			$escape = $options['escape'];
			$attributes = array();

			foreach ($options as $key => $value) {
				if (!isset($exclude[$key]) && $value !== false && $value !== null) {
					$attributes[] = $this->_formatAttribute($key, $value, $escape);
				}
			}
			$out = implode(' ', $attributes);
		} else {
			$out = $options;
		}
		return $out ? $insertBefore . $out . $insertAfter : '';
	}

/**
 * Formats an individual attribute, and returns the string value of the composed attribute.
 * Works with minimized attributes that have the same value as their name such as 'disabled' and 'checked'
 *
 * @param string $key The name of the attribute to create
 * @param string $value The value of the attribute to create.
 * @param boolean $escape Define if the value must be escaped
 * @return string The composed attribute.
 * @deprecated This method will be moved to HtmlHelper in 3.0
 */
	protected function _formatAttribute($key, $value, $escape = true) {
		$attribute = '';
		if (is_array($value)) {
			$value = implode(' ' , $value);
		}

		if (is_numeric($key)) {
			$attribute = sprintf($this->_minimizedAttributeFormat, $value, $value);
		} elseif (in_array($key, $this->_minimizedAttributes)) {
			if ($value === 1 || $value === true || $value === 'true' || $value === '1' || $value == $key) {
				$attribute = sprintf($this->_minimizedAttributeFormat, $key, $key);
			}
		} else {
			$attribute = sprintf($this->_attributeFormat, $key, ($escape ? h($value) : $value));
		}
		return $attribute;
	}

/**
 * Returns a string generated by a helper method
 *
 * This method can be overridden in subclasses to do generalized output post-processing
 *
 * @param string $str String to be output.
 * @return string
 * @deprecated This method will be removed in future versions.
 */
	public function output($str) {
		return $str;
	}

/**
 * Before render callback. beforeRender is called before the view file is rendered.
 *
 * Overridden in subclasses.
 *
 * @param string $viewFile The view file that is going to be rendered
 * @return void
 */
	public function beforeRender($viewFile) {
	}

/**
 * After render callback.  afterRender is called after the view file is rendered
 * but before the layout has been rendered.
 *
 * Overridden in subclasses.
 *
 * @param string $viewFile The view file that was rendered.
 * @return void
 */
	public function afterRender($viewFile) {
	}

/**
 * Before layout callback.  beforeLayout is called before the layout is rendered.
 *
 * Overridden in subclasses.
 *
 * @param string $layoutFile The layout about to be rendered.
 * @return void
 */
	public function beforeLayout($layoutFile) {
	}

/**
 * After layout callback.  afterLayout is called after the layout has rendered.
 *
 * Overridden in subclasses.
 *
 * @param string $layoutFile The layout file that was rendered.
 * @return void
 */
	public function afterLayout($layoutFile) {
	}

/**
 * Before render file callback.
 * Called before any view fragment is rendered.
 *
 * Overridden in subclasses.
 *
 * @param string $viewFile The file about to be rendered.
 * @return void
 */
	public function beforeRenderFile($viewfile) {
	}

/**
 * After render file callback.
 * Called after any view fragment is rendered.
 *
 * Overridden in subclasses.
 *
 * @param string $viewFile The file just be rendered.
 * @param string $content The content that was rendered.
 * @return void
 */
	public function afterRenderFile($viewfile, $content) {
	}


/**
 * Resets the vars used by Helper::clean() to null
 *
 * @return void
 */
	protected function _reset() {
		$this->_tainted = null;
		$this->_cleaned = null;
	}

/**
 * Removes harmful content from output
 *
 * @return void
 */
	protected function _clean() {
		if (get_magic_quotes_gpc()) {
			$this->_cleaned = stripslashes($this->_tainted);
		} else {
			$this->_cleaned = $this->_tainted;
		}

		$this->_cleaned = str_replace(array("&amp;", "&lt;", "&gt;"), array("&amp;amp;", "&amp;lt;", "&amp;gt;"), $this->_cleaned);
		$this->_cleaned = preg_replace('#(&\#*\w+)[\x00-\x20]+;#u', "$1;", $this->_cleaned);
		$this->_cleaned = preg_replace('#(&\#x*)([0-9A-F]+);*#iu', "$1$2;", $this->_cleaned);
		$this->_cleaned = html_entity_decode($this->_cleaned, ENT_COMPAT, "UTF-8");
		$this->_cleaned = preg_replace('#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>#iUu', "$1>", $this->_cleaned);
		$this->_cleaned = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([\`\'\"]*)[\\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu', '$1=$2nojavascript...', $this->_cleaned);
		$this->_cleaned = preg_replace('#([a-z]*)[\x00-\x20]*=([\'\"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iUu', '$1=$2novbscript...', $this->_cleaned);
		$this->_cleaned = preg_replace('#([a-z]*)[\x00-\x20]*=*([\'\"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#iUu', '$1=$2nomozbinding...', $this->_cleaned);
		$this->_cleaned = preg_replace('#([a-z]*)[\x00-\x20]*=([\'\"]*)[\x00-\x20]*data[\x00-\x20]*:#Uu', '$1=$2nodata...', $this->_cleaned);
		$this->_cleaned = preg_replace('#(<[^>]+)style[\x00-\x20]*=[\x00-\x20]*([\`\'\"]*).*expression[\x00-\x20]*\([^>]*>#iU', "$1>", $this->_cleaned);
		$this->_cleaned = preg_replace('#(<[^>]+)style[\x00-\x20]*=[\x00-\x20]*([\`\'\"]*).*behaviour[\x00-\x20]*\([^>]*>#iU', "$1>", $this->_cleaned);
		$this->_cleaned = preg_replace('#(<[^>]+)style[\x00-\x20]*=[\x00-\x20]*([\`\'\"]*).*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*>#iUu', "$1>", $this->_cleaned);
		$this->_cleaned = preg_replace('#</*\w+:\w[^>]*>#i', "", $this->_cleaned);
		do {
			$oldstring = $this->_cleaned;
			$this->_cleaned = preg_replace('#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>#i', "", $this->_cleaned);
		} while ($oldstring != $this->_cleaned);
		$this->_cleaned = str_replace(array("&amp;", "&lt;", "&gt;"), array("&amp;amp;", "&amp;lt;", "&amp;gt;"), $this->_cleaned);
	}

}
