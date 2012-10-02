<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright   Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link        http://cakephp.org CakePHP(tm) Project
 * @package       Cake.View.Helper
 * @since       CakePHP(tm) v 0.10.0.1076
 * @license     MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('ClassRegistry', 'Utility');
App::uses('AppHelper', 'View/Helper');
App::uses('Hash', 'Utility');
App::uses('FormModel', 'View/Helper/Form');
App::uses('FormInput', 'View/Helper/Form');

/**
 * Form helper library.
 *
 * Automatic generation of HTML FORMs from given data.
 *
 * @package       Cake.View.Helper
 * @property      HtmlHelper $Html
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html
 */
class FormHelper extends AppHelper {

/**
 * Other helpers used by FormHelper
 *
 * @var array
 */
	public $helpers = array('Html');

/**
 * List of fields created, used with secure forms.
 *
 * @var array
 */
	public $fields = array();

/**
 * Constant used internally to skip the securing process,
 * and neither add the field to the hash or to the unlocked fields.
 *
 * @var string
 */
	const SECURE_SKIP = 'skip';

/**
 * Defines the type of form being created.  Set by FormHelper::create().
 *
 * @var string
 */
	public $requestType = null;

/**
 * An array of field names that have been excluded from
 * the Token hash used by SecurityComponent's validatePost method
 *
 * @see FormHelper::_secure()
 * @see SecurityComponent::validatePost()
 * @var array
 */
	protected $_unlockedFields = array();

	public $Model;

	protected $_Input;
/**
 * Copies the validationErrors variable from the View object into this instance
 *
 * @param View $View The View this helper is being attached to.
 * @param array $settings Configuration settings for the helper.
 */
	public function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);
		$this->Model = new FormModel($this);
		$this->Model->validationErrors =& $View->validationErrors;
		$this->Input = new FormInput($this);
		$this->Input->viewVars =& $View->viewVars;
	}

/**
 * Returns an HTML FORM element.
 *
 * ### Options:
 *
 * - `type` Form method defaults to POST
 * - `action`  The controller action the form submits to, (optional).
 * - `url`  The url the form submits to. Can be a string or a url array.  If you use 'url'
 *    you should leave 'action' undefined.
 * - `default`  Allows for the creation of Ajax forms. Set this to false to prevent the default event handler.
 *   Will create an onsubmit attribute if it doesn't not exist. If it does, default action suppression
 *   will be appended.
 * - `onsubmit` Used in conjunction with 'default' to create ajax forms.
 * - `inputDefaults` set the default $options for FormHelper::input(). Any options that would
 *   be set when using FormHelper::input() can be set here.  Options set with `inputDefaults`
 *   can be overridden when calling input()
 * - `encoding` Set the accept-charset encoding for the form.  Defaults to `Configure::read('App.encoding')`
 *
 * @param string $model The model object which the form is being defined for.  Should
 *   include the plugin name for plugin forms.  e.g. `ContactManager.Contact`.
 * @param array $options An array of html attributes and options.
 * @return string An formatted opening FORM tag.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#options-for-create
 */
	public function create($model = null, $options = array()) {
		if (is_array($model) && empty($options)) {
			$options = $model;
			$model = null;
		}

		if (empty($model) && $model !== false && !empty($this->request->params['models'])) {
			$model = key($this->request->params['models']);
		} elseif (empty($model) && empty($this->request->params['models'])) {
			$model = false;
		}
		$this->Model->defaultModel = $model;

		$id = $this->getId($model);
		$options = $this->_formOptions($options, $model, $id);

		$append = '';
		switch (strtolower($options['type'])) {
			case 'get':
				$htmlAttributes['method'] = 'get';
			break;
			case 'file':
				$htmlAttributes['enctype'] = 'multipart/form-data';
				$options['type'] = ($id) ? 'put' : 'post';
			case 'post':
			case 'put':
			case 'delete':
				$append .= $this->Input->hidden('_method', array(
					'name' => '_method', 'value' => strtoupper($options['type']), 'id' => null,
					'secure' => self::SECURE_SKIP
				));
			default:
				$htmlAttributes['method'] = 'post';
			break;
		}
		$this->requestType = strtolower($options['type']);

		$action = $this->url($options['action']);
		unset($options['type'], $options['action']);

		if ($options['default'] == false) {
			if (!isset($options['onsubmit'])) {
				$options['onsubmit'] = '';
			}
			$htmlAttributes['onsubmit'] = $options['onsubmit'] . 'event.returnValue = false; return false;';
		}
		unset($options['default']);

		if (!empty($options['encoding'])) {
			$htmlAttributes['accept-charset'] = $options['encoding'];
			unset($options['encoding']);
		}

		$htmlAttributes = array_merge($options, $htmlAttributes);

		$this->fields = array();
		$append .= $this->_csrfField();

		if (!empty($append)) {
			$append = $this->Html->useTag('hiddenblock', $append);
		}

		$this->Model->setEntity($model, true);
		$this->Model->introspect('fields');
		return $this->Html->useTag('form', $action, $htmlAttributes) . $append;
	}

	public function getId($model) {
		$this->Model->setEntity($model, true);
		if ($model === false || !$key = $this->Model->introspect('key')) {
			return false;
		}

		if (!empty($this->request->data[$model][$key]) && !is_array($this->request->data[$model][$key])) {
			return $this->request->data[$model][$key];
		}
		return false;
	}

	protected function _formOptions($options, $model, $id) {
		$options = array_merge(array(
			'type' => ($id && empty($options['action'])) ? 'put' : 'post',
			'action' => null,
			'url' => null,
			'default' => true,
			'encoding' => strtolower(Configure::read('App.encoding')),
			'inputDefaults' => array()),
		$options);
		$this->Input->Options->defaults($options['inputDefaults']);
		unset($options['inputDefaults']);

		if (!isset($options['id'])) {
			$domId = isset($options['action']) ? $options['action'] : $this->request['action'];
			$options['id'] = $this->Model->domId($domId . 'Form');
		}

		$options = $this->_formAction($options, $model, $id);
		unset($options['url']);
		return $options;
	}

	protected function _formAction($options, $model, $id) {
		if ($options['action'] === null && $options['url'] === null) {
			$options['action'] = $this->request->here(false);
			return $options;
		} elseif (is_string($options['url'])) {
			$options['action'] = $options['url'];
			return $options;
		} elseif ($options['url'] && !is_array($options['url'])) {
			return $options;
		}

		if (empty($options['url']['controller'])) {
			if (!empty($model)) {
				$options['url']['controller'] = Inflector::underscore(Inflector::pluralize($model));
			} elseif (!empty($this->request->params['controller'])) {
				$options['url']['controller'] = Inflector::underscore($this->request->params['controller']);
			}
		}
		if (empty($options['action'])) {
			$options['action'] = $this->request->params['action'];
		}

		$plugin = $this->plugin ? Inflector::underscore($this->plugin) : null;
		$actionDefaults = array(
			'plugin' => $plugin,
			'controller' => $this->_View->viewPath,
			'action' => $options['action'],
		);
		$options['action'] = array_merge($actionDefaults, (array)$options['url']);
		if (empty($options['action'][0]) && $id) {
			$options['action'][0] = $id;
		}

		return $options;
	}
/**
 * Return a CSRF input if the _Token is present.
 * Used to secure forms in conjunction with SecurityComponent
 *
 * @return string
 */
	protected function _csrfField() {
		if (empty($this->request->params['_Token'])) {
			return '';
		}
		if (!empty($this->request['_Token']['unlockedFields'])) {
			foreach ((array)$this->request['_Token']['unlockedFields'] as $unlocked) {
				$this->_unlockedFields[] = $unlocked;
			}
		}
		return $this->Input->hidden('_Token.key', array(
			'value' => $this->request->params['_Token']['key'], 'id' => 'Token' . mt_rand(),
			'secure' => self::SECURE_SKIP
		));
	}

/**
 * Closes an HTML form, cleans up values set by FormHelper::create(), and writes hidden
 * input fields where appropriate.
 *
 * If $options is set a form submit button will be created. Options can be either a string or an array.
 *
 * {{{
 * array usage:
 *
 * array('label' => 'save'); value="save"
 * array('label' => 'save', 'name' => 'Whatever'); value="save" name="Whatever"
 * array('name' => 'Whatever'); value="Submit" name="Whatever"
 * array('label' => 'save', 'name' => 'Whatever', 'div' => 'good') <div class="good"> value="save" name="Whatever"
 * array('label' => 'save', 'name' => 'Whatever', 'div' => array('class' => 'good')); <div class="good"> value="save" name="Whatever"
 * }}}
 *
 * @param string|array $options as a string will use $options as the value of button,
 * @return string a closing FORM tag optional submit button.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#closing-the-form
 */
	public function end($options = null) {
		$out = null;
		$submit = null;

		if ($options !== null) {
			$submitOptions = array();
			if (is_string($options)) {
				$submit = $options;
			} else {
				if (isset($options['label'])) {
					$submit = $options['label'];
					unset($options['label']);
				}
				$submitOptions = $options;
			}
			$out .= $this->submit($submit, $submitOptions);
		}
		if (isset($this->request['_Token']) && !empty($this->request['_Token'])) {
			$out .= $this->secure($this->fields);
			$this->fields = array();
		}
		$this->Model->setEntity(null);
		$out .= $this->Html->useTag('formend');

		$this->_View->modelScope = false;
		return $out;
	}

/**
 * Generates a hidden field with a security hash based on the fields used in the form.
 *
 * @param array $fields The list of fields to use when generating the hash
 * @return string A hidden input field with a security hash
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::secure
 */
	public function secure($fields = array()) {
		if (!isset($this->request['_Token']) || empty($this->request['_Token'])) {
			return;
		}
		$locked = array();
		$unlockedFields = $this->_unlockedFields;

		foreach ($fields as $key => $value) {
			if (!is_int($key)) {
				$locked[$key] = $value;
				unset($fields[$key]);
			}
		}

		sort($unlockedFields, SORT_STRING);
		sort($fields, SORT_STRING);
		ksort($locked, SORT_STRING);
		$fields += $locked;

		$locked = implode(array_keys($locked), '|');
		$unlocked = implode($unlockedFields, '|');
		$fields = Security::hash(serialize($fields) . $unlocked . Configure::read('Security.salt'));

		$out = $this->Input->hidden('_Token.fields', array(
			'value' => urlencode($fields . ':' . $locked),
			'id' => 'TokenFields' . mt_rand()
		));
		$out .= $this->Input->hidden('_Token.unlocked', array(
			'value' => urlencode($unlocked),
			'id' => 'TokenUnlocked' . mt_rand()
		));
		return $this->Html->useTag('hiddenblock', $out);
	}

/**
 * Add to or get the list of fields that are currently unlocked.
 * Unlocked fields are not included in the field hash used by SecurityComponent
 * unlocking a field once its been added to the list of secured fields will remove
 * it from the list of fields.
 *
 * @param string $name The dot separated name for the field.
 * @return mixed Either null, or the list of fields.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::unlockField
 */
	public function unlockField($name = null) {
		if ($name === null) {
			return $this->_unlockedFields;
		}
		if (!in_array($name, $this->_unlockedFields)) {
			$this->_unlockedFields[] = $name;
		}
		$index = array_search($name, $this->fields);
		if ($index !== false) {
			unset($this->fields[$index]);
		}
		unset($this->fields[$name]);
	}

	public function securePublic($lock, $field = null, $value = null) {
		return $this->_secure($lock, $field, $value);
	}

/**
 * Determine which fields of a form should be used for hash.
 * Populates $this->fields
 *
 * @param boolean $lock Whether this field should be part of the validation
 *     or excluded as part of the unlockedFields.
 * @param string|array $field Reference to field to be secured.  Should be dot separated to indicate nesting.
 * @param mixed $value Field value, if value should not be tampered with.
 * @return void
 */
	protected function _secure($lock, $field = null, $value = null) {
		if (!$field) {
			$field = $this->Model->entity();
		} elseif (is_string($field)) {
			$field = Hash::filter(explode('.', $field));
		}

		foreach ($this->_unlockedFields as $unlockField) {
			$unlockParts = explode('.', $unlockField);
			if (array_values(array_intersect($field, $unlockParts)) === $unlockParts) {
				return;
			}
		}

		$field = implode('.', $field);
		$field = preg_replace('/(\.\d+)+$/', '', $field);

		if ($lock) {
			if (!in_array($field, $this->fields)) {
				if ($value !== null) {
					return $this->fields[$field] = $value;
				}
				$this->fields[] = $field;
			}
		} else {
			$this->unlockField($field);
		}
	}

/**
 * Returns true if there is an error for the given field, otherwise false
 *
 * @param string $field This should be "Modelname.fieldname"
 * @return boolean If there are errors this method returns true, else false.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::isFieldError
 */
	public function isFieldError($field) {
		$this->Model->setEntity($field);
		return (bool)$this->Model->tagIsInvalid();
	}

/**
 * Returns a formatted error message for given FORM field, NULL if no errors.
 *
 * ### Options:
 *
 * - `escape`  bool  Whether or not to html escape the contents of the error.
 * - `wrap`  mixed  Whether or not the error message should be wrapped in a div. If a
 *   string, will be used as the HTML tag to use.
 * - `class` string  The classname for the error message
 *
 * @param string $field A field name, like "Modelname.fieldname"
 * @param string|array $text Error message as string or array of messages.
 * If array contains `attributes` key it will be used as options for error container
 * @param array $options Rendering options for <div /> wrapper tag
 * @return string If there are errors this method returns an error message, otherwise null.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::error
 */
	public function error($field, $text = null, $options = array()) {
		$this->Model->setEntity($field);
		$error = $this->Model->tagIsInvalid();
		if ($error === false) {
			return null;
		}

		$defaults = array('wrap' => true, 'class' => 'error-message', 'escape' => true);
		$options = array_merge($defaults, $options);

		if (is_array($text)) {
			if (isset($text['attributes']) && is_array($text['attributes'])) {
				$options = array_merge($options, $text['attributes']);
				unset($text['attributes']);
			}
			$tmp = array();
			foreach ($error as $e) {
				if (isset($text[$e])) {
					$tmp[] = $text[$e];
				} else {
					$tmp[] = $e;
				}
			}
			$text = $tmp;
		}

		if ($text !== null) {
			$error = $text;
		}
		if (is_array($error)) {
			foreach ($error as &$e) {
				if (is_numeric($e)) {
					$e = __d('cake', 'Error in field %s', Inflector::humanize($this->Model->field()));
				}
			}
		}
		if ($options['escape']) {
			$error = h($error);
			unset($options['escape']);
		}
		if (is_array($error)) {
			$error = $this->_normalizeError($error, $options);
			unset($options['listOptions']);
		}
		if ($options['wrap']) {
			$tag = is_string($options['wrap']) ? $options['wrap'] : 'div';
			unset($options['wrap']);
			return $this->Html->tag($tag, $error, $options);
		}
		return $error;
	}

	protected function _normalizeError($error, $options) {
		if (count($error) <= 1) {
			return array_pop($error);
		}
		$listParams = $this->_listParams($options);
		array_unshift($listParams, $error);
		return call_user_func_array(array($this->Html, 'nestedList'), $listParams);
	}

	protected function _listParams($options) {
		$listParams = array();
		if (isset($options['listOptions'])) {
			if (is_string($options['listOptions'])) {
				$listParams[] = $options['listOptions'];
			} else {
				if (isset($options['listOptions']['itemOptions'])) {
					$listParams[] = $options['listOptions']['itemOptions'];
					unset($options['listOptions']['itemOptions']);
				} else {
					$listParams[] = array();
				}
				if (isset($options['listOptions']['tag'])) {
					$listParams[] = $options['listOptions']['tag'];
					unset($options['listOptions']['tag']);
				}
				array_unshift($listParams, $options['listOptions']);
			}
		}
		return $listParams;
	}

/**
 * Returns a formatted LABEL element for HTML FORMs. Will automatically generate
 * a `for` attribute if one is not provided.
 *
 * ### Options
 *
 * - `for` - Set the for attribute, if its not defined the for attribute
 *   will be generated from the $fieldName parameter using
 *   FormHelper::domId().
 *
 * Examples:
 *
 * The text and for attribute are generated off of the fieldname
 *
 * {{{
 * echo $this->Form->label('Post.published');
 * <label for="PostPublished">Published</label>
 * }}}
 *
 * Custom text:
 *
 * {{{
 * echo $this->Form->label('Post.published', 'Publish');
 * <label for="PostPublished">Publish</label>
 * }}}
 *
 * Custom class name:
 *
 * {{{
 * echo $this->Form->label('Post.published', 'Publish', 'required');
 * <label for="PostPublished" class="required">Publish</label>
 * }}}
 *
 * Custom attributes:
 *
 * {{{
 * echo $this->Form->label('Post.published', 'Publish', array(
 *		'for' => 'post-publish'
 * ));
 * <label for="post-publish">Publish</label>
 * }}}
 *
 * @param string $fieldName This should be "Modelname.fieldname"
 * @param string $text Text that will appear in the label field.  If
 *   $text is left undefined the text will be inflected from the
 *   fieldName.
 * @param array|string $options An array of HTML attributes, or a string, to be used as a class name.
 * @return string The formatted LABEL element
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::label
 */
	public function label($fieldName = null, $text = null, $options = array()) {
		if (empty($fieldName)) {
			$fieldName = implode('.', $this->Model->entity());
		}

		if ($text === null) {
			if (strpos($fieldName, '.') !== false) {
				$fieldElements = explode('.', $fieldName);
				$text = array_pop($fieldElements);
			} else {
				$text = $fieldName;
			}
			if (substr($text, -3) == '_id') {
				$text = substr($text, 0, -3);
			}
			$text = __(Inflector::humanize(Inflector::underscore($text)));
		}

		if (is_string($options)) {
			$options = array('class' => $options);
		}

		if (isset($options['for'])) {
			$labelFor = $options['for'];
			unset($options['for']);
		} else {
			$labelFor = $this->Model->domId($fieldName);
		}

		return $this->Html->useTag('label', $labelFor, $options, $text);
	}

/**
 * Generate a set of inputs for `$fields`.  If $fields is null the current model
 * will be used.
 *
 * In addition to controller fields output, `$fields` can be used to control legend
 * and fieldset rendering with the `fieldset` and `legend` keys.
 * `$form->inputs(array('legend' => 'My legend'));` Would generate an input set with
 * a custom legend.  You can customize individual inputs through `$fields` as well.
 *
 * {{{
 *	$form->inputs(array(
 *		'name' => array('label' => 'custom label')
 *	));
 * }}}
 *
 * In addition to fields control, inputs() allows you to use a few additional options.
 *
 * - `fieldset` Set to false to disable the fieldset. If a string is supplied it will be used as
 *    the classname for the fieldset element.
 * - `legend` Set to false to disable the legend for the generated input set. Or supply a string
 *    to customize the legend text.
 *
 * @param array $fields An array of fields to generate inputs for, or null.
 * @param array $blacklist a simple array of fields to not create inputs for.
 * @return string Completed form inputs.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::inputs
 */
	public function inputs($fields = null, $blacklist = null) {
		$fieldset = $legend = true;
		if (is_array($fields)) {
			if (array_key_exists('legend', $fields)) {
				$legend = $fields['legend'];
				unset($fields['legend']);
			}

			if (isset($fields['fieldset'])) {
				$fieldset = $fields['fieldset'];
				unset($fields['fieldset']);
			}
		} elseif ($fields !== null) {
			$fieldset = $legend = $fields;
			if (!is_bool($fieldset)) {
				$fieldset = true;
			}
			$fields = null;
		}

		if (empty($fields)) {
			$fields = array_keys($this->Model->introspect('fields'));
		}

		if ($legend === true) {
			$actionName = __d('cake', 'New %s');
			$isEdit = (
				strpos($this->request->params['action'], 'update') !== false ||
				strpos($this->request->params['action'], 'edit') !== false
			);
			if ($isEdit) {
				$actionName = __d('cake', 'Edit %s');
			}
			$modelName = Inflector::humanize(Inflector::underscore($this->Model->model()));
			$legend = sprintf($actionName, __($modelName));
		}

		$out = $this->_generateInputs($fields, $blacklist);

		if (is_string($fieldset)) {
			$fieldsetClass = sprintf(' class="%s"', $fieldset);
		} else {
			$fieldsetClass = '';
		}

		if ($fieldset && $legend) {
			return $this->Html->useTag('fieldset', $fieldsetClass, $this->Html->useTag('legend', $legend) . $out);
		} elseif ($fieldset) {
			return $this->Html->useTag('fieldset', $fieldsetClass, $out);
		}
		return $out;
	}

	protected function _generateInputs($fields, $blacklist) {
		$out = null;
		foreach ($fields as $name => $options) {
			if (is_numeric($name) && !is_array($options)) {
				$name = $options;
				$options = array();
			}
			$entity = explode('.', $name);
			$blacklisted = (
				is_array($blacklist) &&
				(in_array($name, $blacklist) || in_array(end($entity), $blacklist))
			);
			if ($blacklisted) {
				continue;
			}
			$out .= $this->Input->input($name, $options);
		}
		return $out;
	}

/**
 * Creates a `<button>` tag.  The type attribute defaults to `type="submit"`
 * You can change it to a different value by using `$options['type']`.
 *
 * ### Options:
 *
 * - `escape` - HTML entity encode the $title of the button. Defaults to false.
 *
 * @param string $title The button's caption. Not automatically HTML encoded
 * @param array $options Array of options and HTML attributes.
 * @return string A HTML button tag.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::button
 */
	public function button($title, $options = array()) {
		$options += array('type' => 'submit', 'escape' => false, 'secure' => false);
		if ($options['escape']) {
			$title = h($title);
		}
		if (isset($options['name'])) {
			$name = str_replace(array('[', ']'), array('.', ''), $options['name']);
			$this->_secure($options['secure'], $name);
		}
		return $this->Html->useTag('button', $options, $title);
	}

/**
 * Create a `<button>` tag with a surrounding `<form>` that submits via POST.
 *
 * This method creates a `<form>` element. So do not use this method in an already opened form.
 * Instead use FormHelper::submit() or FormHelper::button() to create buttons inside opened forms.
 *
 * ### Options:
 *
 * - `data` - Array with key/value to pass in input hidden
 * - Other options is the same of button method.
 *
 * @param string $title The button's caption. Not automatically HTML encoded
 * @param string|array $url URL as string or array
 * @param array $options Array of options and HTML attributes.
 * @return string A HTML button tag.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::postButton
 */
	public function postButton($title, $url, $options = array()) {
		$out = $this->create(false, array('id' => false, 'url' => $url));
		if (isset($options['data']) && is_array($options['data'])) {
			foreach ($options['data'] as $key => $value) {
				$out .= $this->Input->hidden($key, array('value' => $value, 'id' => false));
			}
			unset($options['data']);
		}
		$out .= $this->button($title, $options);
		$out .= $this->end();
		return $out;
	}

/**
 * Creates an HTML link, but access the url using the method you specify (defaults to POST).
 * Requires javascript to be enabled in browser.
 *
 * This method creates a `<form>` element. So do not use this method inside an existing form.
 * Instead you should add a submit button using FormHelper::submit()
 *
 * ### Options:
 *
 * - `data` - Array with key/value to pass in input hidden
 * - `confirm` - Can be used instead of $confirmMessage.
 * - Other options is the same of HtmlHelper::link() method.
 * - The option `onclick` will be replaced.
 *
 * @param string $title The content to be wrapped by <a> tags.
 * @param string|array $url Cake-relative URL or array of URL parameters, or external URL (starts with http://)
 * @param array $options Array of HTML attributes.
 * @param string $confirmMessage JavaScript confirmation message.
 * @return string An `<a />` element.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::postLink
 */
	public function postLink($title, $url = null, $options = array(), $confirmMessage = false) {
		$requestMethod = 'POST';
		if (!empty($options['method'])) {
			$requestMethod = strtoupper($options['method']);
			unset($options['method']);
		}
		if (!empty($options['confirm'])) {
			$confirmMessage = $options['confirm'];
			unset($options['confirm']);
		}

		$formName = uniqid('post_');
		$formUrl = $this->url($url);
		$out = $this->Html->useTag('form', $formUrl, array(
			'name' => $formName,
			'id' => $formName,
			'style' => 'display:none;',
			'method' => 'post'
		));
		$out .= $this->Html->useTag('hidden', '_method', array(
			'value' => $requestMethod
		));
		$out .= $this->_csrfField();

		$fields = array();
		if (isset($options['data']) && is_array($options['data'])) {
			foreach ($options['data'] as $key => $value) {
				$fields[$key] = $value;
				$out .= $this->Input->hidden($key, array('value' => $value, 'id' => false));
			}
			unset($options['data']);
		}
		$out .= $this->secure($fields);
		$out .= $this->Html->useTag('formend');

		$url = '#';
		$onClick = 'document.' . $formName . '.submit();';
		if ($confirmMessage) {
			$confirmMessage = str_replace(array("'", '"'), array("\'", '\"'), $confirmMessage);
			$options['onclick'] = "if (confirm('{$confirmMessage}')) { {$onClick} }";
		} else {
			$options['onclick'] = $onClick;
		}
		$options['onclick'] .= ' event.returnValue = false; return false;';

		$out .= $this->Html->link($title, $url, $options);
		return $out;
	}

/**
 * Creates a submit button element.  This method will generate `<input />` elements that
 * can be used to submit, and reset forms by using $options.  image submits can be created by supplying an
 * image path for $caption.
 *
 * ### Options
 *
 * - `div` - Include a wrapping div?  Defaults to true.  Accepts sub options similar to
 *   FormHelper::input().
 * - `before` - Content to include before the input.
 * - `after` - Content to include after the input.
 * - `type` - Set to 'reset' for reset inputs.  Defaults to 'submit'
 * - Other attributes will be assigned to the input element.
 *
 * ### Options
 *
 * - `div` - Include a wrapping div?  Defaults to true.  Accepts sub options similar to
 *   FormHelper::input().
 * - Other attributes will be assigned to the input element.
 *
 * @param string $caption The label appearing on the button OR if string contains :// or the
 *  extension .jpg, .jpe, .jpeg, .gif, .png use an image if the extension
 *  exists, AND the first character is /, image is relative to webroot,
 *  OR if the first character is not /, image is relative to webroot/img.
 * @param array $options Array of options.  See above.
 * @return string A HTML submit button
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::submit
 */
	public function submit($caption = null, $options = array()) {
		if (!is_string($caption) && empty($caption)) {
			$caption = __d('cake', 'Submit');
		}
		$out = null;
		$div = true;

		if (isset($options['div'])) {
			$div = $options['div'];
			unset($options['div']);
		}
		$options += array('type' => 'submit', 'before' => null, 'after' => null, 'secure' => false);
		$divOptions = array('tag' => 'div');

		if ($div === true) {
			$divOptions['class'] = 'submit';
		} elseif ($div === false) {
			unset($divOptions);
		} elseif (is_string($div)) {
			$divOptions['class'] = $div;
		} elseif (is_array($div)) {
			$divOptions = array_merge(array('class' => 'submit', 'tag' => 'div'), $div);
		}

		if (isset($options['name'])) {
			$name = str_replace(array('[', ']'), array('.', ''), $options['name']);
			$this->_secure($options['secure'], $name);
		}
		unset($options['secure']);

		$before = $options['before'];
		$after = $options['after'];
		unset($options['before'], $options['after']);

		$isUrl = strpos($caption, '://') !== false;
		$isImage = preg_match('/\.(jpg|jpe|jpeg|gif|png|ico)$/', $caption);

		if ($isUrl || $isImage) {
			$unlockFields = array('x', 'y');
			if (isset($options['name'])) {
				$unlockFields = array(
					$options['name'] . '_x', $options['name'] . '_y'
				);
			}
			foreach ($unlockFields as $ignore) {
				$this->unlockField($ignore);
			}
		}

		if ($isUrl) {
			unset($options['type']);
			$tag = $this->Html->useTag('submitimage', $caption, $options);
		} elseif ($isImage) {
			unset($options['type']);
			if ($caption{0} !== '/') {
				$url = $this->webroot(IMAGES_URL . $caption);
			} else {
				$url = $this->webroot(trim($caption, '/'));
			}
			$url = $this->assetTimestamp($url);
			$tag = $this->Html->useTag('submitimage', $url, $options);
		} else {
			$options['value'] = $caption;
			$tag = $this->Html->useTag('submit', $options);
		}
		$out = $before . $tag . $after;

		if (isset($divOptions)) {
			$tag = $divOptions['tag'];
			unset($divOptions['tag']);
			$out = $this->Html->tag($tag, $out, $divOptions);
		}
		return $out;
	}

/**
 * Gets the data for the current tag
 *
 * @param array|string $options If an array, should be an array of attributes that $key needs to be added to.
 *   If a string or null, will be used as the View entity.
 * @param string $field
 * @param string $key The name of the attribute to be set, defaults to 'value'
 * @return mixed If an array was given for $options, an array with $key set will be returned.
 *   If a string was supplied a string will be returned.
 * @todo Refactor this method to not have as many input/output options.
 */
	public function value($options = array(), $field = null, $key = 'value') {
		if ($options === null) {
			$options = array();
		} elseif (is_string($options)) {
			$field = $options;
			$options = 0;
		}

		if (is_array($options) && isset($options[$key])) {
			return $options;
		}

		if (!empty($field)) {
			$this->Model->setEntity($field);
		}
		$result = null;
		$data = $this->request->data;

		$entity = $this->Model->entity();
		if (!empty($data) && is_array($data) && !empty($entity)) {
			$result = Hash::get($data, implode('.', $entity));
		}

		$habtmKey = $this->Model->field();
		if (empty($result) && isset($data[$habtmKey][$habtmKey]) && is_array($data[$habtmKey])) {
			$result = $data[$habtmKey][$habtmKey];
		} elseif (empty($result) && isset($data[$habtmKey]) && is_array($data[$habtmKey])) {
			if (ClassRegistry::isKeySet($habtmKey)) {
				$model = ClassRegistry::getObject($habtmKey);
				$result = $this->_selectedArray($data[$habtmKey], $model->primaryKey);
			}
		}

		if (is_array($options)) {
			if ($result === null && isset($options['default'])) {
				$result = $options['default'];
			}
			unset($options['default']);
		}

		if (is_array($options)) {
			$options[$key] = $result;
			return $options;
		} else {
			return $result;
		}
	}

/**
 * Transforms a recordset from a hasAndBelongsToMany association to a list of selected
 * options for a multiple select element
 *
 * @param string|array $data
 * @param string $key
 * @return array
 */
	protected function _selectedArray($data, $key = 'id') {
		if (!is_array($data)) {
			$model = $data;
			if (!empty($this->request->data[$model][$model])) {
				return $this->request->data[$model][$model];
			}
			if (!empty($this->request->data[$model])) {
				$data = $this->request->data[$model];
			}
		}
		$array = array();
		if (!empty($data)) {
			foreach ($data as $row) {
				if (isset($row[$key])) {
					$array[$row[$key]] = $row[$key];
				}
			}
		}
		return empty($array) ? null : $array;
	}

	public function __call($method, $params) {
		$options = array();
		if (empty($params)) {
			throw new CakeException(__d('cake_dev', 'Missing field name for FormHelper::%s', $method));
		}
		if (isset($params[1])) {
			$options = $params[1];
		}
		return $this->Input->{$method}($params[0], $options);
	}

	public function radio($fieldName, $options = array(), $attributes = array()) {
		return $this->Input->radio($fieldName, $options, $attributes);
	}

	public function select($fieldName, $options = array(), $attributes = array()) {
		return $this->Input->select($fieldName, $options, $attributes);
	}

	public function year($fieldName, $minYear = null, $maxYear = null, $attributes = array()) {
		return $this->Input->year($fieldName, $minYear, $maxYear, $attributes);
	}

	public function hour($fieldName, $format24Hours = false, $attributes = array()) {
		return $this->Input->hour($fieldName, $format24Hours, $attributes);
	}

	public function dateTime($fieldName, $dateFormat = 'DMY', $timeFormat = '12', $attributes = array()) {
		return $this->Input->dateTime($fieldName, $dateFormat, $timeFormat, $attributes);
	}
}
