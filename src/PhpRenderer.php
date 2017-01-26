<?php

namespace View;

/**
 * Simple engine to render PHP templates.
 *
 * This is inspired from a few other projects:
 * - Slim PHP View https://github.com/slimphp/PHP-View
 * - Symfony Templating Component https://github.com/symfony/templating
 *
 * The purpose of this class is to have something extremely simple, for very simple
 * uses cases, without all the bells and whistle.
 */
class PhpRenderer // implements \ArrayAccess
{
	static public $CONTENT_NAME = '_content';

	/**
	 * @var array global variables accessible by every templates
	 */
	protected $globals = [];

	/**
	 * @var string
	 */
	protected $path = '';

	protected $decorators = [];
	protected $layout = '';
	protected $depth = 0;

	protected $current = null;
	protected $current_layout = null;
	protected $current_globals = null;

	/**
	 * Constructor.
	 */
	public function __construct($path = '', array $globals = [])
	{
		$this->path = $path ? realpath(rtrim($path, DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR : '';
		$this->globals = $globals;
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
}