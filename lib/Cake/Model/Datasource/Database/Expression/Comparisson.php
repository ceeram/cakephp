<?php
/**
 * 
 * PHP Version 5.4
 *
 * CakePHP(tm) Tests <http://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The Open Group Test Suite License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/2.0/en/development/testing.html CakePHP(tm) Tests
 * @package       Cake.Test.Case.Model.Datasource.Database
 * @since         CakePHP(tm) v 3.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Model\Datasource\Database\Expression;

use Cake\Model\Datasource\Database\Expression;

class Comparisson extends QueryExpression {

	protected $_field;

	protected $_value;

	protected $_type;

	public function __construct(array $condition, $types = [], $conjuntion = '=') {
		parent::__construct($condition, $types, $conjuntion);
	}

	public function field($field) {
		$this->_field = $field;
	}

	public function value($value) {
		$this->_value = $value;
	}

	public function getField() {
		return $this->_field;
	}

	public function getValue() {
		return $this->_value;
	}

	public function sql() {
		$value = $this->_value;
		$template = '%s %s (%s)';
		if (!($this->_value instanceof Expression)) {
			$value = $this->_bindValue($this->_field,$value, $this->_type);
			$template = '%s %s %s';
		}

		return sprintf($template, $this->_field, $this->_conjunction, $value);
	}

	public function count() {
		return 1;
	}

	protected function _addConditions(array $condition, array $types) {
		$this->_conditions[] = current($condition);
		$this->_field = key($condition);
		$this->_value = current($condition);

		if (isset($types[$this->_field])) {
			$this->_type = current($types);
		}
	}

}