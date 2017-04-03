<?php

/**
 * Licensed under the MIT license.
 *
 * For the full copyright and license information, please view the LICENSE file.
 *
 * @author Rémi Lanvin <remi@dmbook.pro>
 * @link https://github.com/dmbookpro/php-view
 */

namespace View;

/**
 * Simple engine to render PHP templates.
 *
 * This is inspired from a few other projects:
 * - Slim PHP View https://github.com/slimphp/PHP-View
 * - Symfony Templating Component https://github.com/symfony/templating
 *
 * The purpose of this class is to have something extremely simple, for very simple
 * uses cases, without all the bells and whistle of a full templating engine.
 */
class PhpRenderer
{
	static public $CONTENT_NAME = '_content';

	/**
	 * @var array Global variables accessible by every templates
	 */
	protected $globals = [];

	/**
	 * @var string Path where the templates are stored
	 */
	protected $path = '';

	/**
	 * @var string Name of the layout file, to decorate the templates with
	 */
	protected $layout = '';

	/**
	 * @internal
	 * @var int Current rendering depth. Each call to render() increases the value.
	 * The layout will only be renderer after the higher template (depth=1) is done
	 */
	protected $depth = 0;

	/**
	 * @internal
	 * @var string Store the current high-level template being renderered
	 */
	protected $current = null;

	/**
	 * @internal
	 * @var string Store the layout from the current template. Calling setLayout()
	 * from within a template will only change this, and not the global layout
	 * for any subsequent rendering
	 */
	protected $current_layout = null;

	/**
	 * @internal
	 * @var array Store the current globals. Calling setGlobals() from within
	 * a template will only change this, and not the global globals array.
	 */
	protected $current_globals = null;

	/**
	 * @var array An array of helpers (callable)
	 */
	protected $helpers = [];

	/**
	 * Constructor.
	 *
	 * @param string $path The path where the templates are stored.
	 * @param array $globals An array of global variables (can also use setGlobals)
	 * @param string $layout The name of the layout file to be used (null if no layout)
	 */
	public function __construct($path = '', array $globals = [], $layout = null)
	{
		if ( ! is_string($path) ) {
			throw new \InvalidArgumentException('path must be a string');
		}

		$this->path = $path ? realpath(rtrim($path, DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR : '';
		$this->globals = $globals;
		$this->layout = $layout;

		$this->addHelper('e', function($string) {
			return htmlspecialchars($string, ENT_COMPAT | ENT_HTML5, 'UTF-8');
		});

		$this->addHelper('j', function($string) {
			return addslashes($string);
		});
	}

///////////////////////////////////////////////////////////////////////////////
// Global variables

	/**
	 * Set global variables accessible by every templates
	 */
	public function setGlobals(array $globals)
	{
		if ( $this->current ) {
			$this->current_globals = $globals;
		}
		else {
			$this->globals = $globals;
		}
		return $this;
	}

	public function addGlobals(array $globals)
	{
		if ( $this->current ) {
			$this->current_globals = array_merge($this->current_globals, $globals);
		}
		else {
			$this->globals = array_merge($this->globals, $globals);
		}
		return $this;
	}

	public function setGlobal($name, $value)
	{
		if ( $this->current ) {
			$this->current_globals[$name] = $value;
		}
		else {
			$this->globals[$name] = $value;
		}
		return $this;
	}

	public function getGlobals()
	{
		return array_merge($this->globals, $this->current_globals);
	}

	public function __get($name)
	{
		if ( array_key_exists($name, $this->current_globals) ) {
			return $this->current_globals[$name];
		}
		elseif ( array_key_exists($name, $this->globals) ) {
			return $this->globals[$name];
		}
		return null;
	}

	public function __set($name, $value)
	{
		return $this->setGlobal($name, $value);
	}

	public function setLayout($template)
	{
		if ( $this->current ) {
			$this->current_layout = $template;
		}
		else {
			$this->layout = $template;
		}
		return $this;
	}

///////////////////////////////////////////////////////////////////////////////
// Rendering

	public function render($template, array $data = [])
	{
		if ( ! $template ) {
			throw new \InvalidArgumentException('Template name cannot be empty');
		}

		// process file path
		$file = $this->path.$template;
		if ( ! is_readable($file) ) {
			throw new \RuntimeException("The file $file does not exists or is not readable");
		}

		// first call we initialize the context
		if ( $this->depth == 0 ) {
			$this->current = $file;
			$this->current_layout = $this->layout;
			$this->current_globals = [];
		}

		$this->depth += 1;

		$content = $this->evaluate($file, array_merge($this->getGlobals(), $data));

		if ( $content === false ) {
			throw new \RuntimeException("Rendering of $file failed");
		}

		// decorate (layout)
		// we only decorate if depth is 1 (so we don't decorate sub templates, nor the layout itself)
		if ( $this->depth == 1 && $this->current_layout ) {
			$content = $this->render($this->current_layout, array_merge(
				$this->getGlobals(),
				$data,
				[self::$CONTENT_NAME => $content]
			));
		}

		$this->depth -= 1;

		if ( $this->depth == 0 ) {
			$this->current = null;
			$this->current_layout = null;
			$this->current_globals = [];
		}

		return $content;
	}

	protected $_file = null;
	protected $_data = null;

	protected function evaluate($file, array $data = [])
	{
		if ( array_key_exists('this', $data) ) {
			throw new \InvalidArgumentException('"this" is a reserved keyword');
		}

		$this->_file = $file;
		$this->_data = $data;
		unset($file,$data);

		extract($this->_data, EXTR_SKIP);
		$this->_data = null;

		ob_start();
		require $this->_file;

		$this->_file = null;
		return ob_get_clean();
	}

///////////////////////////////////////////////////////////////////////////////
// Helpers

	public function addHelper($name, callable $helper)
	{
		$this->helpers[$name] = \Closure::bind($helper, $this);
		return $this;
	}

	public function __call($name, $arguments)
	{
		if ( ! array_key_exists($name, $this->helpers) ) {
			throw new \BadMethodCallException("Unknown helper: $name - loaded helpers are: ".implode(', ',array_keys($this->helpers)));
		}

		return call_user_func_array($this->helpers[$name], $arguments);
	}
}