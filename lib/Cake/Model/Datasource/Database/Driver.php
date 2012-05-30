<?php

namespace Cake\Model\Datasource\Database;

/**
 * Represents a database diver containing all specificities for
 * a database engine including its SQL dialect
 *
 **/
abstract class Driver {

/**
 * Set specific driver configs
 *
 * @param array $config configuretion to be used for creating connection
 * @return array Configs array
 **/
	public abstract function config(array $config);

/**
 * Returns wheter php is able to use this driver for connecting to database
 *
 * @return boolean true if it is valid to use this driver
 **/
	public abstract function enabled();

}