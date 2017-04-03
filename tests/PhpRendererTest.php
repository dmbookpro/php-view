<?php

use View\PhpRenderer;

class PhpRendererTest extends PHPUnit_Framework_TestCase
{
	public function getRenderer()
	{
		return new PhpRenderer(__DIR__.'/templates');
	}

////////////////////////////////////////////////////////////////////////////////

	/**
	 * @expectedException RuntimeException
	 */
	public function testRenderBadFile()
	{
		$view = new PhpRenderer();
		$view->render('Non existant file');
	}

	public function testPath()
	{
		$view = new PhpRenderer();
		$this->assertNotEmpty($view->render(__DIR__.'/templates/helloworld.php'));

		$view = new PhpRenderer(__DIR__);
		$this->assertNotEmpty($view->render('templates/helloworld.php'));

		$view = new PhpRenderer(__DIR__.'/templates');
		$this->assertNotEmpty($view->render('helloworld.php'));

		$view = new PhpRenderer(__DIR__.'/templates/');
		$this->assertNotEmpty($view->render('helloworld.php'));
	}

	public function testRender()
	{
		$template = __DIR__.'/templates/hello.php';

		$view = new PhpRenderer();

		$this->assertEquals('Hello, World!',$view->render($template));
		$this->assertEquals('Hello, Sean!',$view->render($template, ['hello' => 'Sean']));
	}

	public function testRenderWithGlobals()
	{
		$template = __DIR__.'/templates/hello.php';

		$view = new PhpRenderer();
		$view->setGlobals(['hello' => 'John']);

		$this->assertEquals('Hello, John!', $view->render($template));
		$this->assertEquals('Hello, Sean!',$view->render($template, ['hello' => 'Sean']));
	}

	public function testRenderRecursive()
	{
		$template = 'render.php';

		$view = $this->getRenderer();
		$this->assertEquals('Hello, World!', $view->render($template));
		$this->assertEquals('Hello, World!', $view->render($template, ['hello' => 'John']), 'Local variables is not passed to subtemplates');

		$view->setGlobals(['hello' => 'John']);
		$this->assertEquals('Hello, John!', $view->render($template), 'Globals variables are passed to subtemplates');
	}

	public function testRenderLayout()
	{
		$template = 'hello.php';
		$layout = 'layout.php';

		$view = $this->getRenderer();
		$view->setLayout($layout);

		$this->assertEquals('<strong>Hello, John!</strong>', $view->render($template, ['hello' => 'John']));
		$this->assertEquals('<strong>Hello, John!</strong>', $view->render($template, ['hello' => 'John']), 'Re-rendering keeps the layout settings');
	}

	public function testRenderLayoutAndRecursive()
	{
		$template = 'render.php';
		$layout = 'layout.php';

		$view = $this->getRenderer();
		$view->setGlobals(['hello' => 'Sean']);
		$view->setLayout($layout);

		$this->assertEquals('<strong>Hello, Sean!</strong>', $view->render($template));
	}

	public function testRenderLayoutRendersItself()
	{
		$template = 'helloworld.php';
		$view = $this->getRenderer();
		$view->setLayout($template);

		$this->assertEquals('Hello, World!', $view->render($template));
	}

	public function testRenderCustomLayout()
	{
		$template = 'setlayout.php';

		$view = $this->getRenderer();

		$this->assertEquals('<strong>Hello, World!</strong>', $view->render($template));
	}

	public function testRenderCustomLayoutDoesntAffectDefaultLayout()
	{
		$view = $this->getRenderer();
		$view->setLayout('layout2.php');

		$this->assertEquals('<strong>Hello, World!</strong>', $view->render('setlayout.php'));
		$this->assertEquals('<html>Hello, World!</html>', $view->render('hello.php'));
	}

	public function testPassVariablesToLayout()
	{
		$view = $this->getRenderer();
		$view->setLayout('layout3.php');
		$this->assertEquals(
			"<html>\n<head>\n<title>Greetings</title>\n</head>\n<body>Hello, World!</body>\n</html>",
			$view->render('setglobal.php', ['title' => 'Greetings'])
		);

	}

///////////////////////////////////////////////////////////////////////////////
// Helpers

	public function addHelper()
	{
		$view = $this->getRenderer();
		$view->addHelper('answer', function() {
			return '42';
		});

		$this->assertEquals('42', $view->answer());
	}

///////////////////////////////////////////////////////////////////////////////
// Defaut helpers

	public function escapedStrings()
	{
		return [
			["<strong>I'm</strong>", "&lt;strong&gt;I'm&lt;/strong&gt;", "<strong>I\'m</strong>"],
			['"', '&quot;', '\"'],
			['é', 'é', 'é'],
			['&', '&amp;', '&']
		];
	}

	/**
	 * @dataProvider escapedStrings
	 */
	public function testEHelper($original, $escaped)
	{
		$view = new PhpRenderer();
		$this->assertEquals($escaped, $view->e($original));
	}

	/**
	 * @dataProvider escapedStrings
	 */
	public function testJHelper($original, $ignore, $escaped)
	{
		$view = new PhpRenderer();
		$this->assertEquals($escaped, $view->j($original));
	}
}