<?php
App::uses('InputOptions', 'View/Helper/Form');

class FormInput {

	protected $_Form;

/**
 * Options used by DateTime fields
 *
 * @var array
 */
	protected $_options = array(
		'day' => array(), 'minute' => array(), 'hour' => array(),
		'month' => array(), 'year' => array(), 'meridian' => array()
	);

/**
 *
 * @param FormHelper $Form
 */
	public function __construct(FormHelper $Form) {
		$this->_Form = $Form;
		$this->Options = new InputOptions($Form);
	}

/**
 * Generates a form input element complete with label and wrapper div
 *
 * ### Options
 *
 * See each field type method for more information. Any options that are part of
 * $attributes or $options for the different **type** methods can be included in `$options` for input().i
 * Additionally, any unknown keys that are not in the list below, or part of the selected type's options
 * will be treated as a regular html attribute for the generated input.
 *
 * - `type` - Force the type of widget you want. e.g. `type => 'select'`
 * - `label` - Either a string label, or an array of options for the label. See FormHelper::label()
 * - `div` - Either `false` to disable the div, or an array of options for the div.
 *	See HtmlHelper::div() for more options.
 * - `options` - for widgets that take options e.g. radio, select
 * - `error` - control the error message that is produced
 * - `empty` - String or boolean to enable empty select box options.
 * - `before` - Content to place before the label + input.
 * - `after` - Content to place after the label + input.
 * - `between` - Content to place between the label + input.
 * - `format` - format template for element order. Any element that is not in the array, will not be in the output.
 *	- Default input format order: array('before', 'label', 'between', 'input', 'after', 'error')
 *	- Default checkbox format order: array('before', 'input', 'between', 'label', 'after', 'error')
 *	- Hidden input will not be formatted
 *	- Radio buttons cannot have the order of input and label elements controlled with these settings.
 *
 * @param string $fieldName This should be "Modelname.fieldname"
 * @param array $options Each type of input takes different options.
 * @return string Completed form widget.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#creating-form-elements
 */
	public function input($fieldName, $options = array()) {
		$this->_Form->Model->setEntity($fieldName);
		$options = $this->Options->parse($fieldName, $options);

		$divOptions = $this->Options->div($options);
		unset($options['div']);

		$label = $this->Options->label($fieldName, $options);
		if ($label !== false) {
			$label = $this->_inputLabel($fieldName, $label, $options);
		}
		if ($options['type'] !== 'radio') {
			unset($options['label']);
		}

		$out = array('before' => $options['before'], 'label' => $label, 'between' => $options['between'], 'after' => $options['after']);
		$format = $this->_getFormat($options);

		unset($options['before'], $options['between'], $options['after'], $options['format']);

		$out['error'] = $this->_errorMessage($fieldName, $options);
		unset($options['error']);
		if ($out['error']) {
			$divOptions = $this->Options->addClass($divOptions, 'error');
		}

		if ($options['type'] === 'radio' && isset($out['between'])) {
			$options['between'] = $out['between'];
			$out['between'] = null;
		}
		$out['input'] = $this->_parseInput($fieldName, $options);

		$output = '';
		foreach ($format as $element) {
			$output .= $out[$element];
		}

		if (!empty($divOptions['tag'])) {
			$tag = $divOptions['tag'];
			unset($divOptions['tag']);
			$output = $this->_Form->Html->tag($tag, $output, $divOptions);
		}
		return $output;
	}

/**
 * Generate a label for an input() call.
 *
 * $options can contain a hash of id overrides.  These overrides will be
 * used instead of the generated values if present.
 *
 * @param string $fieldName
 * @param string $label
 * @param array $options Options for the label element.
 * @return string Generated label element
 * @deprecated 'NONE' option is deprecated and will be removed in 3.0
 */
	protected function _inputLabel($fieldName, $label, $options) {
		$labelAttributes = $this->_Form->Model->domId(array(), 'for');
		$labelAttributes = $this->_dateTimeLabel($labelAttributes, $options);

		if (is_array($label)) {
			$labelText = null;
			if (isset($label['text'])) {
				$labelText = $label['text'];
				unset($label['text']);
			}
			$labelAttributes = array_merge($labelAttributes, $label);
		} else {
			$labelText = $label;
		}

		if (isset($options['id']) && is_string($options['id'])) {
			$labelAttributes = array_merge($labelAttributes, array('for' => $options['id']));
		}
		return $this->_Form->label($fieldName, $labelText, $labelAttributes);
	}

/**
 *
 * @param type $labelAttributes
 * @param type $options
 * @return type
 */
	protected function _dateTimeLabel($labelAttributes, $options) {
		if (!in_array($options['type'], array('date', 'datetime', 'time'))) {
			return $labelAttributes;
		}

		$idKey = $this->_extractDateTime($options);
		$labelAttributes['for'] .= ucfirst($idKey);
		if (isset($options['id']) && isset($options['id'][$idKey])) {
			$labelAttributes['for'] = $options['id'][$idKey];
		}
		return $labelAttributes;
	}

/**
 *
 * @param type $options
 * @return string
 */
	protected function _extractDateTime($options) {
		if ($options['type'] === 'time') {
			return 'hour';
		}
		$keys = array('D' => 'day', 'Y' => 'year', 'M' => 'month', 'H' => 'hour');
		$firstInput = 'M';
		if (
			array_key_exists('dateFormat', $options) &&
			($options['dateFormat'] === null || $options['dateFormat'] === 'NONE')
		) {
			$firstInput = 'H';
		} elseif (!empty($options['dateFormat'])) {
			$firstInput = substr($options['dateFormat'], 0, 1);
		}
		return $keys[$firstInput];
	}

/**
 *
 * @param type $options
 * @return array
 */
	protected function _getFormat($options) {
		if ($options['type'] == 'hidden') {
			return array('input');
		}
		if (is_array($options['format']) && in_array('input', $options['format'])) {
			return $options['format'];
		}
		if ($options['type'] == 'checkbox') {
			return array('before', 'input', 'between', 'label', 'after', 'error');
		}
		return array('before', 'label', 'between', 'input', 'after', 'error');
	}

	protected function _errorMessage($fieldName, $options) {
		$error = $this->Options->extract('error', $options, null);
		if ($options['type'] == 'hidden' || $error === false) {
			return null;
		}
		$errMsg = $this->_Form->error($fieldName, $error);
		if ($errMsg) {
			return $errMsg;
		}
		return null;
	}

/**
 *
 * @param type $args
 * @return type
 */
	protected function _parseInput($fieldName, $options) {
		$type = $options['type'];
		unset($options['type']);

		$selected = $this->Options->extract('selected', $options, null);
		unset($options['selected']);

		if (in_array($type, array('datetime', 'date', 'time'))) {
			$dateFormat = $this->Options->extract('dateFormat', $options, 'MDY');
			$timeFormat = $this->Options->extract('timeFormat', $options, 12);
			unset($options['dateFormat'], $options['timeFormat']);
		}
		switch ($type) {
			case 'select':
				$options += array('options' => array(), 'value' => $selected);
			case 'radio':
				$list = $options['options'];
				unset($options['options']);
				return $this->{$type}($fieldName, $list, $options);
			case 'time':
				$options['value'] = $selected;
				return $this->dateTime($fieldName, null, $timeFormat, $options);
			case 'date':
				$options['value'] = $selected;
				return $this->dateTime($fieldName, $dateFormat, null, $options);
			case 'datetime':
				$options['value'] = $selected;
				return $this->dateTime($fieldName, $dateFormat, $timeFormat, $options);
			case 'url':
				return $this->text($fieldName, array('type' => 'url') + $options);
			case 'textarea':
				$options += array('cols' => '30', 'rows' => '6');
			default:
				return $this->{$type}($fieldName, $options);
		}
	}

/**
 * Missing method handler - implements various simple input types. Is used to create inputs
 * of various types.  e.g. `$this->Form->text();` will create `<input type="text" />` while
 * `$this->Form->range();` will create `<input type="range" />`
 *
 * ### Usage
 *
 * `$this->Form->search('User.query', array('value' => 'test'));`
 *
 * Will make an input like:
 *
 * `<input type="search" id="UserQuery" name="data[User][query]" value="test" />`
 *
 * The first argument to an input type should always be the fieldname, in `Model.field` format.
 * The second argument should always be an array of attributes for the input.
 *
 * @param string $method Method name / input type to make.
 * @param array $params Parameters for the method call
 * @return string Formatted input method.
 * @throws CakeException When there are no params for the method call.
 */
	public function __call($method, $params) {
		$options = array();
		if (empty($params)) {
			throw new CakeException(__d('cake_dev', 'Missing field name for FormHelper::%s', $method));
		}
		if (isset($params[1])) {
			$options = $params[1];
		}
		if (!isset($options['type'])) {
			$options['type'] = $method;
		}
		$options = $this->_init($params[0], $options);
		return $this->_Form->Html->useTag('input', $options['name'], array_diff_key($options, array('name' => '')));
	}

/**
 * Creates a textarea widget.
 *
 * ### Options:
 *
 * - `escape` - Whether or not the contents of the textarea should be escaped. Defaults to true.
 *
 * @param string $fieldName Name of a field, in the form "Modelname.fieldname"
 * @param array $options Array of HTML attributes, and special options above.
 * @return string A generated HTML text input element
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::textarea
 */
	public function textarea($fieldName, $options = array()) {
		$options = $this->_init($fieldName, $options);
		$value = null;

		if (array_key_exists('value', $options)) {
			$value = $options['value'];
			if (!array_key_exists('escape', $options) || $options['escape'] !== false) {
				$value = h($value);
			}
			unset($options['value']);
		}
		return $this->_Form->Html->useTag('textarea', $options['name'], array_diff_key($options, array('type' => '', 'name' => '')), $value);
	}

/**
 * Creates a hidden input field.
 *
 * @param string $fieldName Name of a field, in the form of "Modelname.fieldname"
 * @param array $options Array of HTML attributes.
 * @return string A generated hidden input
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::hidden
 */
	public function hidden($fieldName, $options = array()) {
		$secure = true;

		if (isset($options['secure'])) {
			$secure = $options['secure'];
			unset($options['secure']);
		}
		$options = $this->_init($fieldName, array_merge(
			$options, array('secure' => FormHelper::SECURE_SKIP)
		));

		if ($secure && $secure !== FormHelper::SECURE_SKIP) {
			$this->_Form->securePublic(true, null, '' . $options['value']);
		}

		return $this->_Form->Html->useTag('hidden', $options['name'], array_diff_key($options, array('name' => '')));
	}

/**
 * Creates file input widget.
 *
 * @param string $fieldName Name of a field, in the form "Modelname.fieldname"
 * @param array $options Array of HTML attributes.
 * @return string A generated file input.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::file
 */
	public function file($fieldName, $options = array()) {
		$options += array('secure' => true);
		$secure = $options['secure'];
		$options['secure'] = FormHelper::SECURE_SKIP;

		$options = $this->_init($fieldName, $options);
		$field = $this->_Form->Model->entity();

		foreach (array('name', 'type', 'tmp_name', 'error', 'size') as $suffix) {
			$this->_Form->securePublic($secure, array_merge($field, array($suffix)));
		}

		return $this->_Form->Html->useTag('file', $options['name'], array_diff_key($options, array('name' => '')));
	}

/**
 * Returns a formatted SELECT element.
 *
 * ### Attributes:
 *
 * - `showParents` - If included in the array and set to true, an additional option element
 *   will be added for the parent of each option group. You can set an option with the same name
 *   and it's key will be used for the value of the option.
 * - `multiple` - show a multiple select box.  If set to 'checkbox' multiple checkboxes will be
 *   created instead.
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `escape` - If true contents of options will be HTML entity encoded. Defaults to true.
 * - `value` The selected value of the input.
 * - `class` - When using multiple = checkbox the classname to apply to the divs. Defaults to 'checkbox'.
 * - `disabled` - Control the disabled attribute.  When creating a select box, set to true to disable the
 *   select box.  When creating checkboxes, `true` will disable all checkboxes. You can also set disabled
 *   to a list of values you want to disable when creating checkboxes.
 *
 * ### Using options
 *
 * A simple array will create normal options:
 *
 * {{{
 * $options = array(1 => 'one', 2 => 'two);
 * $this->Form->select('Model.field', $options));
 * }}}
 *
 * While a nested options array will create optgroups with options inside them.
 * {{{
 * $options = array(
 *  1 => 'bill',
 *  'fred' => array(
 *     2 => 'fred',
 *     3 => 'fred jr.'
 *  )
 * );
 * $this->Form->select('Model.field', $options);
 * }}}
 *
 * In the above `2 => 'fred'` will not generate an option element.  You should enable the `showParents`
 * attribute to show the fred option.
 *
 * If you have multiple options that need to have the same value attribute, you can
 * use an array of arrays to express this:
 *
 * {{{
 * $options = array(
 *  array('name' => 'United states', 'value' => 'USA'),
 *  array('name' => 'USA', 'value' => 'USA'),
 * );
 * }}}
 *
 * @param string $fieldName Name attribute of the SELECT
 * @param array $options Array of the OPTION elements (as 'value'=>'Text' pairs) to be used in the
 *	SELECT element
 * @param array $attributes The HTML attributes of the select element.
 * @return string Formatted SELECT element
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#options-for-select-checkbox-and-radio-inputs
 */
	public function select($fieldName, $options = array(), $attributes = array()) {
		$select = array();
		$style = null;
		$tag = null;
		$attributes += array(
			'class' => null,
			'escape' => true,
			'secure' => true,
			'empty' => '',
			'showParents' => false,
			'hiddenField' => true,
			'disabled' => false
		);

		$escapeOptions = $this->Options->extract('escape', $attributes);
		$secure = $this->Options->extract('secure', $attributes);
		$showEmpty = $this->Options->extract('empty', $attributes);
		$showParents = $this->Options->extract('showParents', $attributes);
		$hiddenField = $this->Options->extract('hiddenField', $attributes);
		unset($attributes['escape'], $attributes['secure'], $attributes['empty'], $attributes['showParents'], $attributes['hiddenField']);
		$id = $this->Options->extract('id', $attributes);

		$attributes = $this->_init($fieldName, array_merge(
			(array)$attributes, array('secure' => FormHelper::SECURE_SKIP)
		));

		if (is_string($options) && isset($this->_options[$options])) {
			$options = $this->_generateOptions($options);
		} elseif (!is_array($options)) {
			$options = array();
		}
		if (isset($attributes['type'])) {
			unset($attributes['type']);
		}

		if (!empty($attributes['multiple'])) {
			$style = ($attributes['multiple'] === 'checkbox') ? 'checkbox' : null;
			$template = ($style) ? 'checkboxmultiplestart' : 'selectmultiplestart';
			$tag = $template;
			if ($hiddenField) {
				$hiddenAttributes = array(
					'value' => '',
					'id' => $attributes['id'] . ($style ? '' : '_'),
					'secure' => false,
					'name' => $attributes['name']
				);
				$select[] = $this->hidden(null, $hiddenAttributes);
			}
		} else {
			$tag = 'selectstart';
		}

		if (!empty($tag) || isset($template)) {
			$hasOptions = (count($options) > 0 || $showEmpty);
			// Secure the field if there are options, or its a multi select.
			// Single selects with no options don't submit, but multiselects do.
			if (
				(!isset($secure) || $secure == true) &&
				empty($attributes['disabled']) &&
				(!empty($attributes['multiple']) || $hasOptions)
			) {
				$this->_Form->securePublic(true);
			}
			$select[] = $this->_Form->Html->useTag($tag, $attributes['name'], array_diff_key($attributes, array('name' => '', 'value' => '')));
		}
		$emptyMulti = (
			$showEmpty !== null && $showEmpty !== false && !(
				empty($showEmpty) && (isset($attributes) &&
				array_key_exists('multiple', $attributes))
			)
		);

		if ($emptyMulti) {
			$showEmpty = ($showEmpty === true) ? '' : $showEmpty;
			$options = array('' => $showEmpty) + $options;
		}

		if (!$id) {
			$attributes['id'] = Inflector::camelize($attributes['id']);
		}

		$select = array_merge($select, $this->_selectOptions(
			array_reverse($options, true),
			array(),
			$showParents,
			array(
				'escape' => $escapeOptions,
				'style' => $style,
				'name' => $attributes['name'],
				'value' => $attributes['value'],
				'class' => $attributes['class'],
				'id' => $attributes['id'],
				'disabled' => $attributes['disabled'],
			)
		));

		$template = ($style == 'checkbox') ? 'checkboxmultipleend' : 'selectend';
		$select[] = $this->_Form->Html->useTag($template);
		return implode("\n", $select);
	}

/**
 * Returns a SELECT element for days.
 *
 * ### Attributes:
 *
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `value` The selected value of the input.
 *
 * @param string $fieldName Prefix name for the SELECT element
 * @param array $attributes HTML attributes for the select element
 * @return string A generated day select box.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::day
 */
	public function day($fieldName = null, $attributes = array()) {
		$attributes += array('empty' => true, 'value' => null);
		$attributes = $this->_dateTimeSelected('day', $fieldName, $attributes);

		if (strlen($attributes['value']) > 2) {
			$attributes['value'] = date('d', strtotime($attributes['value']));
		} elseif ($attributes['value'] === false) {
			$attributes['value'] = null;
		}
		return $this->select($fieldName . ".day", $this->_generateOptions('day'), $attributes);
	}

/**
 * Returns a SELECT element for years
 *
 * ### Attributes:
 *
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `orderYear` - Ordering of year values in select options.
 *   Possible values 'asc', 'desc'. Default 'desc'
 * - `value` The selected value of the input.
 *
 * @param string $fieldName Prefix name for the SELECT element
 * @param integer $minYear First year in sequence
 * @param integer $maxYear Last year in sequence
 * @param array $attributes Attribute array for the select elements.
 * @return string Completed year select input
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::year
 */
	public function year($fieldName, $minYear = null, $maxYear = null, $attributes = array()) {
		$attributes += array('empty' => true, 'value' => null);
		if ((empty($attributes['value']) || $attributes['value'] === true) && $value = $this->_Form->value($fieldName)) {
			if (is_array($value)) {
				extract($value);
				$attributes['value'] = $year;
			} else {
				if (empty($value)) {
					if (!$attributes['empty'] && !$maxYear) {
						$attributes['value'] = 'now';

					} elseif (!$attributes['empty'] && $maxYear && !$attributes['value']) {
						$attributes['value'] = $maxYear;
					}
				} else {
					$attributes['value'] = $value;
				}
			}
		}

		if (strlen($attributes['value']) > 4 || $attributes['value'] === 'now') {
			$attributes['value'] = date('Y', strtotime($attributes['value']));
		} elseif ($attributes['value'] === false) {
			$attributes['value'] = null;
		}
		$yearOptions = array('min' => $minYear, 'max' => $maxYear, 'order' => 'desc');
		if (isset($attributes['orderYear'])) {
			$yearOptions['order'] = $attributes['orderYear'];
			unset($attributes['orderYear']);
		}
		return $this->select(
			$fieldName . '.year', $this->_generateOptions('year', $yearOptions),
			$attributes
		);
	}

/**
 * Returns a SELECT element for months.
 *
 * ### Attributes:
 *
 * - `monthNames` - If false, 2 digit numbers will be used instead of text.
 *   If a array, the given array will be used.
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `value` The selected value of the input.
 *
 * @param string $fieldName Prefix name for the SELECT element
 * @param array $attributes Attributes for the select element
 * @return string A generated month select dropdown.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::month
 */
	public function month($fieldName, $attributes = array()) {
		$attributes += array('empty' => true, 'value' => null);
		$attributes = $this->_dateTimeSelected('month', $fieldName, $attributes);

		if (strlen($attributes['value']) > 2) {
			$attributes['value'] = date('m', strtotime($attributes['value']));
		} elseif ($attributes['value'] === false) {
			$attributes['value'] = null;
		}
		$defaults = array('monthNames' => true);
		$attributes = array_merge($defaults, (array)$attributes);
		$monthNames = $attributes['monthNames'];
		unset($attributes['monthNames']);

		return $this->select(
			$fieldName . ".month",
			$this->_generateOptions('month', array('monthNames' => $monthNames)),
			$attributes
		);
	}

/**
 * Returns a SELECT element for hours.
 *
 * ### Attributes:
 *
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `value` The selected value of the input.
 *
 * @param string $fieldName Prefix name for the SELECT element
 * @param boolean $format24Hours True for 24 hours format
 * @param array $attributes List of HTML attributes
 * @return string Completed hour select input
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::hour
 */
	public function hour($fieldName, $format24Hours = false, $attributes = array()) {
		$attributes += array('empty' => true, 'value' => null);
		$attributes = $this->_dateTimeSelected('hour', $fieldName, $attributes);

		if (strlen($attributes['value']) > 2) {
			if ($format24Hours) {
				$attributes['value'] = date('H', strtotime($attributes['value']));
			} else {
				$attributes['value'] = date('g', strtotime($attributes['value']));
			}
		} elseif ($attributes['value'] === false) {
			$attributes['value'] = null;
		}
		return $this->select(
			$fieldName . ".hour",
			$this->_generateOptions($format24Hours ? 'hour24' : 'hour'),
			$attributes
		);
	}

/**
 * Returns a SELECT element for minutes.
 *
 * ### Attributes:
 *
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `value` The selected value of the input.
 *
 * @param string $fieldName Prefix name for the SELECT element
 * @param string $attributes Array of Attributes
 * @return string Completed minute select input.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::minute
 */
	public function minute($fieldName, $attributes = array()) {
		$attributes += array('empty' => true, 'value' => null);
		$attributes = $this->_dateTimeSelected('min', $fieldName, $attributes);

		if (strlen($attributes['value']) > 2) {
			$attributes['value'] = date('i', strtotime($attributes['value']));
		} elseif ($attributes['value'] === false) {
			$attributes['value'] = null;
		}
		$minuteOptions = array();

		if (isset($attributes['interval'])) {
			$minuteOptions['interval'] = $attributes['interval'];
			unset($attributes['interval']);
		}
		return $this->select(
			$fieldName . ".min", $this->_generateOptions('minute', $minuteOptions),
			$attributes
		);
	}

/**
 * Selects values for dateTime selects.
 *
 * @param string $select Name of element field. ex. 'day'
 * @param string $fieldName Name of fieldName being generated ex. Model.created
 * @param array $attributes Array of attributes, must contain 'empty' key.
 * @return array Attributes array with currently selected value.
 */
	protected function _dateTimeSelected($select, $fieldName, $attributes) {
		if ((empty($attributes['value']) || $attributes['value'] === true) && $value = $this->_Form->value($fieldName)) {
			if (is_array($value) && isset($value[$select])) {
				$attributes['value'] = $value[$select];
			} else {
				if (empty($value)) {
					if (!$attributes['empty']) {
						$attributes['value'] = 'now';
					}
				} else {
					$attributes['value'] = $value;
				}
			}
		}
		return $attributes;
	}

/**
 * Returns a SELECT element for AM or PM.
 *
 * ### Attributes:
 *
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `value` The selected value of the input.
 *
 * @param string $fieldName Prefix name for the SELECT element
 * @param string $attributes Array of Attributes
 * @return string Completed meridian select input
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::meridian
 */
	public function meridian($fieldName, $attributes = array()) {
		$attributes += array('empty' => true, 'value' => null);
		if ((empty($attributes['value']) || $attributes['value'] === true) && $value = $this->_Form->value($fieldName)) {
			if (is_array($value)) {
				extract($value);
				$attributes['value'] = $meridian;
			} else {
				if (empty($value)) {
					if (!$attributes['empty']) {
						$attributes['value'] = date('a');
					}
				} else {
					$attributes['value'] = date('a', strtotime($value));
				}
			}
		}

		if ($attributes['value'] === false) {
			$attributes['value'] = null;
		}
		return $this->select(
			$fieldName . ".meridian", $this->_generateOptions('meridian'),
			$attributes
		);
	}

/**
 * Returns a set of SELECT elements for a full datetime setup: day, month and year, and then time.
 *
 * ### Attributes:
 *
 * - `monthNames` If false, 2 digit numbers will be used instead of text.
 *   If a array, the given array will be used.
 * - `minYear` The lowest year to use in the year select
 * - `maxYear` The maximum year to use in the year select
 * - `interval` The interval for the minutes select. Defaults to 1
 * - `separator` The contents of the string between select elements. Defaults to '-'
 * - `empty` - If true, the empty select option is shown.  If a string,
 *   that string is displayed as the empty element.
 * - `value` | `default` The default value to be used by the input.  A value in `$this->data`
 *   matching the field name will override this value.  If no default is provided `time()` will be used.
 *
 * @param string $fieldName Prefix name for the SELECT element
 * @param string $dateFormat DMY, MDY, YMD, or null to not generate date inputs.
 * @param string $timeFormat 12, 24, or null to not generate time inputs.
 * @param string $attributes array of Attributes
 * @return string Generated set of select boxes for the date and time formats chosen.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#FormHelper::dateTime
 */
	public function dateTime($fieldName, $dateFormat = 'DMY', $timeFormat = '12', $attributes = array()) {
		$attributes += array('empty' => true, 'value' => null);
		$year = $month = $day = $hour = $min = $meridian = null;

		if (empty($attributes['value'])) {
			$attributes = $this->_Form->value($attributes, $fieldName);
		}

		if ($attributes['value'] === null && $attributes['empty'] != true) {
			$attributes['value'] = time();
		}

		if (!empty($attributes['value'])) {
			if (is_array($attributes['value'])) {
				extract($attributes['value']);
			} else {
				if (is_numeric($attributes['value'])) {
					$attributes['value'] = strftime('%Y-%m-%d %H:%M:%S', $attributes['value']);
				}
				$meridian = 'am';
				$pos = strpos($attributes['value'], '-');
				if ($pos !== false) {
					$date = explode('-', $attributes['value']);
					$days = explode(' ', $date[2]);
					$day = $days[0];
					$month = $date[1];
					$year = $date[0];
				} else {
					$days[1] = $attributes['value'];
				}

				if (!empty($timeFormat)) {
					$time = explode(':', $days[1]);

					if (($time[0] > 12) && $timeFormat == '12') {
						$time[0] = $time[0] - 12;
						$meridian = 'pm';
					} elseif ($time[0] == '12' && $timeFormat == '12') {
						$meridian = 'pm';
					} elseif ($time[0] == '00' && $timeFormat == '12') {
						$time[0] = 12;
					} elseif ($time[0] >= 12) {
						$meridian = 'pm';
					}
					if ($time[0] == 0 && $timeFormat == '12') {
						$time[0] = 12;
					}
					$hour = $min = null;
					if (isset($time[1])) {
						$hour = $time[0];
						$min = $time[1];
					}
				}
			}
		}

		$elements = array('Day', 'Month', 'Year', 'Hour', 'Minute', 'Meridian');
		$defaults = array(
			'minYear' => null, 'maxYear' => null, 'separator' => '-',
			'interval' => 1, 'monthNames' => true
		);
		$attributes = array_merge($defaults, (array)$attributes);
		if (isset($attributes['minuteInterval'])) {
			$attributes['interval'] = $attributes['minuteInterval'];
			unset($attributes['minuteInterval']);
		}
		$minYear = $attributes['minYear'];
		$maxYear = $attributes['maxYear'];
		$separator = $attributes['separator'];
		$interval = $attributes['interval'];
		$monthNames = $attributes['monthNames'];
		$attributes = array_diff_key($attributes, $defaults);

		if (isset($attributes['id'])) {
			if (is_string($attributes['id'])) {
				// build out an array version
				foreach ($elements as $element) {
					$selectAttrName = 'select' . $element . 'Attr';
					${$selectAttrName} = $attributes;
					${$selectAttrName}['id'] = $attributes['id'] . $element;
				}
			} elseif (is_array($attributes['id'])) {
				// check for missing ones and build selectAttr for each element
				$attributes['id'] += array(
					'month' => '', 'year' => '', 'day' => '',
					'hour' => '', 'minute' => '', 'meridian' => ''
				);
				foreach ($elements as $element) {
					$selectAttrName = 'select' . $element . 'Attr';
					${$selectAttrName} = $attributes;
					${$selectAttrName}['id'] = $attributes['id'][strtolower($element)];
				}
			}
		} else {
			// build the selectAttrName with empty id's to pass
			foreach ($elements as $element) {
				$selectAttrName = 'select' . $element . 'Attr';
				${$selectAttrName} = $attributes;
			}
		}

		if (is_array($attributes['empty'])) {
			$attributes['empty'] += array(
				'month' => true, 'year' => true, 'day' => true,
				'hour' => true, 'minute' => true, 'meridian' => true
			);
			foreach ($elements as $element) {
				$selectAttrName = 'select' . $element . 'Attr';
				${$selectAttrName}['empty'] = $attributes['empty'][strtolower($element)];
			}
		}

		$selects = array();
		foreach (preg_split('//', $dateFormat, -1, PREG_SPLIT_NO_EMPTY) as $char) {
			switch ($char) {
				case 'Y':
					$selectYearAttr['value'] = $year;
					$selects[] = $this->year(
						$fieldName, $minYear, $maxYear, $selectYearAttr
					);
				break;
				case 'M':
					$selectMonthAttr['value'] = $month;
					$selectMonthAttr['monthNames'] = $monthNames;
					$selects[] = $this->month($fieldName, $selectMonthAttr);
				break;
				case 'D':
					$selectDayAttr['value'] = $day;
					$selects[] = $this->day($fieldName, $selectDayAttr);
				break;
			}
		}
		$opt = implode($separator, $selects);

		if (!empty($interval) && $interval > 1 && !empty($min)) {
			$min = round($min * (1 / $interval)) * $interval;
		}
		$selectMinuteAttr['interval'] = $interval;
		switch ($timeFormat) {
			case '24':
				$selectHourAttr['value'] = $hour;
				$selectMinuteAttr['value'] = $min;
				$opt .= $this->hour($fieldName, true, $selectHourAttr) . ':' .
				$this->minute($fieldName, $selectMinuteAttr);
			break;
			case '12':
				$selectHourAttr['value'] = $hour;
				$selectMinuteAttr['value'] = $min;
				$selectMeridianAttr['value'] = $meridian;
				$opt .= $this->hour($fieldName, false, $selectHourAttr) . ':' .
				$this->minute($fieldName, $selectMinuteAttr) . ' ' .
				$this->meridian($fieldName, $selectMeridianAttr);
			break;
			default:
				$opt .= '';
			break;
		}
		return $opt;
	}

/**
 * Returns an array of formatted OPTION/OPTGROUP elements
 *
 * @param array $elements
 * @param array $parents
 * @param boolean $showParents
 * @param array $attributes
 * @return array
 */
	protected function _selectOptions($elements = array(), $parents = array(), $showParents = null, $attributes = array()) {
		$select = array();
		$attributes = array_merge(
			array('escape' => true, 'style' => null, 'value' => null, 'class' => null),
			$attributes
		);
		$selectedIsEmpty = ($attributes['value'] === '' || $attributes['value'] === null);
		$selectedIsArray = is_array($attributes['value']);

		foreach ($elements as $name => $title) {
			$htmlOptions = array();
			if (is_array($title) && (!isset($title['name']) || !isset($title['value']))) {
				if (!empty($name)) {
					if ($attributes['style'] === 'checkbox') {
						$select[] = $this->_Form->Html->useTag('fieldsetend');
					} else {
						$select[] = $this->_Form->Html->useTag('optiongroupend');
					}
					$parents[] = $name;
				}
				$select = array_merge($select, $this->_selectOptions(
					$title, $parents, $showParents, $attributes
				));

				if (!empty($name)) {
					$name = $attributes['escape'] ? h($name) : $name;
					if ($attributes['style'] === 'checkbox') {
						$select[] = $this->_Form->Html->useTag('fieldsetstart', $name);
					} else {
						$select[] = $this->_Form->Html->useTag('optiongroup', $name, '');
					}
				}
				$name = null;
			} elseif (is_array($title)) {
				$htmlOptions = $title;
				$name = $title['value'];
				$title = $title['name'];
				unset($htmlOptions['name'], $htmlOptions['value']);
			}

			if ($name !== null) {
				if (
					(!$selectedIsArray && !$selectedIsEmpty && (string)$attributes['value'] == (string)$name) ||
					($selectedIsArray && in_array($name, $attributes['value']))
				) {
					if ($attributes['style'] === 'checkbox') {
						$htmlOptions['checked'] = true;
					} else {
						$htmlOptions['selected'] = 'selected';
					}
				}

				if ($showParents || (!in_array($title, $parents))) {
					$title = ($attributes['escape']) ? h($title) : $title;

					if ($attributes['style'] === 'checkbox') {
						$htmlOptions['value'] = $name;

						$disabledType = null;
						$hasDisabled = !empty($attributes['disabled']);
						if ($hasDisabled) {
							$disabledType = gettype($attributes['disabled']);
						}
						if (
							$hasDisabled &&
							$disabledType === 'array' &&
							in_array($htmlOptions['value'], $attributes['disabled'])
						) {
							$htmlOptions['disabled'] = 'disabled';
						}
						if ($hasDisabled && $disabledType !== 'array') {
							$htmlOptions['disabled'] = $attributes['disabled'] === true ? 'disabled' : $attributes['disabled'];
						}

						$tagName = $attributes['id'] . Inflector::camelize(Inflector::slug($name));
						$htmlOptions['id'] = $tagName;
						$label = array('for' => $tagName);

						if (isset($htmlOptions['checked']) && $htmlOptions['checked'] === true) {
							$label['class'] = 'selected';
						}

						$name = $attributes['name'];

						if (empty($attributes['class'])) {
							$attributes['class'] = 'checkbox';
						} elseif ($attributes['class'] === 'form-error') {
							$attributes['class'] = 'checkbox ' . $attributes['class'];
						}
						$label = $this->_Form->label(null, $title, $label);
						$item = $this->_Form->Html->useTag('checkboxmultiple', $name, $htmlOptions);
						$select[] = $this->_Form->Html->div($attributes['class'], $item . $label);
					} else {
						$select[] = $this->_Form->Html->useTag('selectoption', $name, $htmlOptions, $title);
					}
				}
			}
		}

		return array_reverse($select, true);
	}

/**
 * Generates option lists for common <select /> menus
 *
 * @param string $name
 * @param array $options
 * @return array
 */
	protected function _generateOptions($name, $options = array()) {
		if (!empty($this->options[$name])) {
			return $this->options[$name];
		}
		$data = array();

		switch ($name) {
			case 'minute':
				if (isset($options['interval'])) {
					$interval = $options['interval'];
				} else {
					$interval = 1;
				}
				$i = 0;
				while ($i < 60) {
					$data[sprintf('%02d', $i)] = sprintf('%02d', $i);
					$i += $interval;
				}
			break;
			case 'hour':
				for ($i = 1; $i <= 12; $i++) {
					$data[sprintf('%02d', $i)] = $i;
				}
			break;
			case 'hour24':
				for ($i = 0; $i <= 23; $i++) {
					$data[sprintf('%02d', $i)] = $i;
				}
			break;
			case 'meridian':
				$data = array('am' => 'am', 'pm' => 'pm');
			break;
			case 'day':
				$min = 1;
				$max = 31;

				if (isset($options['min'])) {
					$min = $options['min'];
				}
				if (isset($options['max'])) {
					$max = $options['max'];
				}

				for ($i = $min; $i <= $max; $i++) {
					$data[sprintf('%02d', $i)] = $i;
				}
			break;
			case 'month':
				if ($options['monthNames'] === true) {
					$data['01'] = __d('cake', 'January');
					$data['02'] = __d('cake', 'February');
					$data['03'] = __d('cake', 'March');
					$data['04'] = __d('cake', 'April');
					$data['05'] = __d('cake', 'May');
					$data['06'] = __d('cake', 'June');
					$data['07'] = __d('cake', 'July');
					$data['08'] = __d('cake', 'August');
					$data['09'] = __d('cake', 'September');
					$data['10'] = __d('cake', 'October');
					$data['11'] = __d('cake', 'November');
					$data['12'] = __d('cake', 'December');
				} elseif (is_array($options['monthNames'])) {
					$data = $options['monthNames'];
				} else {
					for ($m = 1; $m <= 12; $m++) {
						$data[sprintf("%02s", $m)] = strftime("%m", mktime(1, 1, 1, $m, 1, 1999));
					}
				}
			break;
			case 'year':
				$current = intval(date('Y'));

				$min = !isset($options['min']) ? $current - 20 : (int)$options['min'];
				$max = !isset($options['max']) ? $current + 20 : (int)$options['max'];

				if ($min > $max) {
					list($min, $max) = array($max, $min);
				}
				for ($i = $min; $i <= $max; $i++) {
					$data[$i] = $i;
				}
				if ($options['order'] != 'asc') {
					$data = array_reverse($data, true);
				}
			break;
		}
		$this->_options[$name] = $data;
		return $this->_options[$name];
	}

/**
 * Creates a checkbox input widget.
 *
 * ### Options:
 *
 * - `value` - the value of the checkbox
 * - `checked` - boolean indicate that this checkbox is checked.
 * - `hiddenField` - boolean to indicate if you want the results of checkbox() to include
 *    a hidden input with a value of ''.
 * - `disabled` - create a disabled input.
 * - `default` - Set the default value for the checkbox.  This allows you to start checkboxes
 *    as checked, without having to check the POST data.  A matching POST data value, will overwrite
 *    the default value.
 *
 * @param string $fieldName Name of a field, like this "Modelname.fieldname"
 * @param array $options Array of HTML attributes.
 * @return string An HTML text input element.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#options-for-select-checkbox-and-radio-inputs
 */
	public function checkbox($fieldName, $options = array()) {
		$valueOptions = array();
		if (isset($options['default'])) {
			$valueOptions['default'] = $options['default'];
			unset($options['default']);
		}

		$options = $this->_init($fieldName, $options) + array('hiddenField' => true);
		$value = current($this->_Form->value($valueOptions));
		$output = "";

		if (empty($options['value'])) {
			$options['value'] = 1;
		}
		if (
			(!isset($options['checked']) && !empty($value) && $value == $options['value']) ||
			!empty($options['checked'])
		) {
			$options['checked'] = 'checked';
		}
		if ($options['hiddenField']) {
			$hiddenOptions = array(
				'id' => $options['id'] . '_',
				'name' => $options['name'],
				'value' => ($options['hiddenField'] !== true ? $options['hiddenField'] : '0'),
				'secure' => false
			);
			if (isset($options['disabled']) && $options['disabled'] == true) {
				$hiddenOptions['disabled'] = 'disabled';
			}
			$output = $this->hidden($fieldName, $hiddenOptions);
		}
		unset($options['hiddenField']);

		return $output . $this->_Form->Html->useTag('checkbox', $options['name'], array_diff_key($options, array('name' => '')));
	}

/**
 * Creates a set of radio widgets. Will create a legend and fieldset
 * by default.  Use $options to control this
 *
 * ### Attributes:
 *
 * - `separator` - define the string in between the radio buttons
 * - `between` - the string between legend and input set
 * - `legend` - control whether or not the widget set has a fieldset & legend
 * - `value` - indicate a value that is should be checked
 * - `label` - boolean to indicate whether or not labels for widgets show be displayed
 * - `hiddenField` - boolean to indicate if you want the results of radio() to include
 *    a hidden input with a value of ''. This is useful for creating radio sets that non-continuous
 * - `disabled` - Set to `true` or `disabled` to disable all the radio buttons.
 * - `empty` - Set to `true` to create a input with the value '' as the first option.  When `true`
 *   the radio label will be 'empty'.  Set this option to a string to control the label value.
 *
 * @param string $fieldName Name of a field, like this "Modelname.fieldname"
 * @param array $options Radio button options array.
 * @param array $attributes Array of HTML attributes, and special attributes above.
 * @return string Completed radio widget set.
 * @link http://book.cakephp.org/2.0/en/core-libraries/helpers/form.html#options-for-select-checkbox-and-radio-inputs
 */
	public function radio($fieldName, $options = array(), $attributes = array()) {
		$attributes = $this->_init($fieldName, $attributes);

		$showEmpty = $this->Options->extract('empty', $attributes);
		if ($showEmpty) {
			$showEmpty = ($showEmpty === true) ? __('empty') : $showEmpty;
			$options = array('' => $showEmpty) + $options;
		}
		unset($attributes['empty']);

		$legend = false;
		if (isset($attributes['legend'])) {
			$legend = $attributes['legend'];
			unset($attributes['legend']);
		} elseif (count($options) > 1) {
			$legend = __(Inflector::humanize($this->_Form->Model->field()));
		}

		$label = true;
		if (isset($attributes['label'])) {
			$label = $attributes['label'];
			unset($attributes['label']);
		}

		$separator = null;
		if (isset($attributes['separator'])) {
			$separator = $attributes['separator'];
			unset($attributes['separator']);
		}

		$between = null;
		if (isset($attributes['between'])) {
			$between = $attributes['between'];
			unset($attributes['between']);
		}

		$value = null;
		if (isset($attributes['value'])) {
			$value = $attributes['value'];
		} else {
			$value = $this->_Form->value($fieldName);
		}

		$disabled = array();
		if (isset($attributes['disabled'])) {
			$disabled = $attributes['disabled'];
		}

		$out = array();

		$hiddenField = isset($attributes['hiddenField']) ? $attributes['hiddenField'] : true;
		unset($attributes['hiddenField']);

		foreach ($options as $optValue => $optTitle) {
			$optionsHere = array('value' => $optValue);

			if (isset($value) && strval($optValue) === strval($value)) {
				$optionsHere['checked'] = 'checked';
			}
			if ($disabled && (!is_array($disabled) || in_array($optValue, $disabled))) {
				$optionsHere['disabled'] = true;
			}
			$tagName = Inflector::camelize(
				$attributes['id'] . '_' . Inflector::slug($optValue)
			);

			if ($label) {
				$optTitle = $this->_Form->Html->useTag('label', $tagName, '', $optTitle);
			}
			$allOptions = array_merge($attributes, $optionsHere);
			$out[] = $this->_Form->Html->useTag('radio', $attributes['name'], $tagName,
				array_diff_key($allOptions, array('name' => '', 'type' => '', 'id' => '')),
				$optTitle
			);
		}
		$hidden = null;

		if ($hiddenField) {
			if (!isset($value) || $value === '') {
				$hidden = $this->hidden($fieldName, array(
					'id' => $attributes['id'] . '_', 'value' => '', 'name' => $attributes['name']
				));
			}
		}
		$out = $hidden . implode($separator, $out);

		if ($legend) {
			$out = $this->_Form->Html->useTag('fieldset', '', $this->_Form->Html->useTag('legend', $legend) . $between . $out);
		}
		return $out;
	}

/**
 * Sets field defaults and adds field to form security input hash
 *
 * ### Options
 *
 * - `secure` - boolean whether or not the field should be added to the security fields.
 *   Disabling the field using the `disabled` option, will also omit the field from being
 *   part of the hashed key.
 *
 * @param string $field Name of the field to initialize options for.
 * @param array $options Array of options to append options into.
 * @return array Array of options for the input.
 */
	protected function _init($field, $options = array()) {
		if (isset($options['secure'])) {
			$secure = $options['secure'];
			unset($options['secure']);
		} else {
			$secure = !empty($this->_Form->request['_Token']);
		}

		$options = $this->_Form->Model->initInputField($field, $options);
		if ($this->_Form->Model->tagIsInvalid() !== false) {
			$options = $this->Options->addClass($options, 'form-error');
		}
		if (!empty($options['disabled']) || $secure === FormHelper::SECURE_SKIP) {
			return $options;
		}

		$fieldName = null;
		if (!empty($options['name'])) {
			preg_match_all('/\[(.*?)\]/', $options['name'], $matches);
			if (isset($matches[1])) {
				$fieldName = $matches[1];
			}
		}

		$this->_Form->securePublic($secure, $fieldName);
		return $options;
	}
}