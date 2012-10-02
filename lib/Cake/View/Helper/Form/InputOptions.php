<?php
class InputOptions {

/**
 * Persistent default options used by input(). Set by FormHelper::create().
 *
 * @var array
 */
	protected $_defaults = array();

	protected $_Form;

	public function __construct(FormHelper $Form) {
		$this->_Form = $Form;
	}
/**
 * Set/Get input defaults for form elements
 *
 * @param array $defaults New default values
 * @param boolean Merge with current defaults
 * @return array input defaults
 */
	public function defaults($defaults = null, $merge = false) {
		if (is_null($defaults)) {
			return $this->_defaults;
		}
		if (!$merge) {
			return $this->_defaults = (array)$defaults;
		}
		return $this->_defaults = array_merge($this->_defaults, (array)$defaults);
	}

/**
 *
 * @param type $fieldName
 * @param type $options
 * @return array
 */
	public function parse($fieldName, $options) {
		$options = array_merge(
			array('before' => null, 'between' => null, 'after' => null, 'format' => null),
			$this->_defaults,
			$options
		);

		if (!isset($options['type'])) {
			$options = $this->_magicOptions($options);
		}

		if (in_array($options['type'], array('checkbox', 'radio', 'select'))) {
			$options = $this->_optionsOptions($options);
		}

		if (isset($options['rows']) || isset($options['cols'])) {
			$options['type'] = 'textarea';
		}

		if ($options['type'] === 'datetime' || $options['type'] === 'date' || $options['type'] === 'time' || $options['type'] === 'select') {
			$options += array('empty' => false);
		}
		return $options;
	}

/**
 *
 * @param type $options
 * @return array
 */
	protected function _magicOptions($options) {
		$modelKey = $this->_Form->Model->model();
		$fieldKey = $this->_Form->Model->field();
		$options['type'] = 'text';
		if (isset($options['options'])) {
			$options['type'] = 'select';
		} elseif (in_array($fieldKey, array('psword', 'passwd', 'password'))) {
			$options['type'] = 'password';
		} elseif (isset($options['checked'])) {
			$options['type'] = 'checkbox';
		} else {
			$options = $this->_introspectField($options);
		}
		if ($options['type'] === 'hidden') {
			return $options;
		}

		if (preg_match('/_id$/', $fieldKey)) {
			$options['type'] = 'select';
		}

		if ($modelKey === $fieldKey) {
			$options['type'] = 'select';
			if (!isset($options['multiple'])) {
				$options['multiple'] = 'multiple';
			}
			return $options;
		}
		if ($options['type'] === 'text') {
			$options = $this->_optionsOptions($options);
		}
		$options = $this->_maxLength($options);
		return $options;
	}

/**
 *
 * @param type $options
 * @return array
 */
	protected function _optionsOptions($options) {
		if (isset($options['options'])) {
			return $options;
		}
		$varName = Inflector::variable(
			Inflector::pluralize(preg_replace('/_id$/', '', $this->_Form->Model->field()))
		);
		$varOptions = isset($this->_Form->Input->viewVars[$varName]) ? $this->_Form->Input->viewVars[$varName] : null;
		if (!is_array($varOptions)) {
			return $options;
		}
		if ($options['type'] !== 'radio') {
			$options['type'] = 'select';
		}
		$options['options'] = $varOptions;
		return $options;
	}

/**
 *
 * @param type $options
 * @return array
 */
	protected function _maxLength($options) {
		if (array_key_exists('maxlength', $options)) {
			return $options;
		}
		$fieldDef = $this->_Form->Model->introspect('fields', $this->_Form->Model->field());
		if (!isset($fieldDef['length']) || !is_scalar($fieldDef['length'])) {
			return $options;
		}

		if ($options['type'] == 'text') {
			$options['maxlength'] = $fieldDef['length'];
		}
		if ($fieldDef['type'] == 'float') {
			$options['maxlength'] = array_sum(explode(',', $fieldDef['length'])) + 1;
		}
		return $options;
	}

	protected function _introspectField($options) {
		$fieldKey = $this->_Form->Model->field();

		$fieldDef = $this->_Form->Model->introspect('fields', $fieldKey);
		if (!$fieldDef) {
			return $options;
		}

		$modelKey = $this->_Form->Model->model();
		$primaryKey = $this->_Form->Model->fieldset[$modelKey]['key'];
		if ($fieldKey == $primaryKey) {
			$options['type'] = 'hidden';
			return $options;
		}

		$map = array(
			'string' => 'text', 'datetime' => 'datetime',
			'boolean' => 'checkbox', 'timestamp' => 'datetime',
			'text' => 'textarea', 'time' => 'time',
			'date' => 'date', 'float' => 'number',
			'integer' => 'number'
		);
		$type = $fieldDef['type'];
		if (isset($map[$type])) {
			$options['type'] = $map[$type];
		}

		if (
			$options['type'] === 'number' &&
			$type === 'float' &&
			!isset($options['step'])
		) {
			$options['step'] = 'any';
		}
		return $options;
	}

/**
 *
 * @param array $options
 * @return array
 */
	public function div($options) {
		if ($options['type'] === 'hidden') {
			return array();
		}
		$div = $this->extract('div', $options, true);
		if (!$div) {
			return array();
		}

		$divOptions = array('class' => 'input', 'tag' => 'div');
		$divOptions = $this->addClass($divOptions, $options['type']);
		if (is_string($div)) {
			$divOptions['class'] = $div;
		} elseif (is_array($div)) {
			$divOptions = array_merge($divOptions, $div);
		}

		if ($this->_Form->Model->introspect('validates', $this->_Form->Model->field())) {
			$divOptions = $this->addClass($divOptions, 'required');
		}
		return $divOptions;
	}

/**
 * Extracts a single option from an options array.
 *
 * @param string $name The name of the option to pull out.
 * @param array $options The array of options you want to extract.
 * @param mixed $default The default option value
 * @return mixed the contents of the option or default
 */
	public function extract($name, $options, $default = null) {
		if (array_key_exists($name, $options)) {
			return $options[$name];
		}
		return $default;
	}

/**
 *
 * @param type $fieldName
 * @param type $options
 * @return boolean|string false or Generated label element
 */
	public function label($fieldName, $options) {
		if ($options['type'] === 'radio') {
			return false;
		}

		$label = null;
		if (isset($options['label'])) {
			$label = $options['label'];
		}

		return $label;
	}

/**
 * Adds the given class to the element options
 *
 * @param array $options Array options/attributes to add a class to
 * @param string $class The classname being added.
 * @param string $key the key to use for class.
 * @return array Array of options with $key set.
 */
	public function addClass($options = array(), $class = null, $key = 'class') {
		if (isset($options[$key]) && trim($options[$key]) != '') {
			$options[$key] .= ' ' . $class;
		} else {
			$options[$key] = $class;
		}
		return $options;
	}
}