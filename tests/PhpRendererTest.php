<?php

use View\PhpRenderer;

class PhpRendererTest extends PHPUnit_Framework_TestCase
{
	protected $file = '';

	public function setUp()
	{
		$this->file = '/tmp/'.uniqid().'.php';
		$this->file2 = '/tmp/'.uniqid().'.php';
		$this->file3 = '/tmp/'.uniqid().'.php';
		$this->file4 = '/tmp/'.uniqid().'.php';
		touch($this->file);
		touch($this->file2);
		touch($this->file3);
		touch($this->file4);
	}

	public function tearDown()
	{
		unlink($this->file);
		unlink($this->file2);
		unlink($this->file3);
		unlink($this->file4);
	}

////////////////////////////////////////////////////////////////////////////////

	public function testRenderBadFile()
	{
		$view = new PhpRenderer();
		try {
			$view->render('Non existant file');
			$this->fail('Expected exception has not been thrown');
		} catch ( RuntimeException $e ) {}
	}

	public function testRender()
	{
		file_put_contents($this->file, '<?=$hello?>');
		$view = new PhpRenderer();

		$this->assertEmpty($view->render($this->file, ['hello' => '']));
		$this->assertEquals('World',$view->render($this->file, ['hello' => 'World']));
	}

	public function testRenderWithGlobals()
	{
		file_put_contents($this->file, '<?=$hello?>');
		$view = new PhpRenderer();
		$view->setGlobals(['hello' => 'World']);

		$this->assertEquals('World', $view->render($this->file));
		$this->assertEquals('World!',$view->render($this->file, ['hello' => 'World!']));
	}

	public function testRenderRecursive()
	{
		file_put_contents($this->file, '<?=$this->render("'.$this->file2.'")?>');
		file_put_contents($this->file2, '<?=isset($hello)?$hello:""?>');

		$view = new PhpRenderer();
		$this->assertEquals('', $view->render($this->file));
		$this->assertEquals('', $view->render($this->file, ['hello' => 'World']), 'Local variables is not passed to subtemplates');

		$view->setGlobals(['hello' => 'World']);
		$this->assertEquals('World', $view->render($this->file), 'Globals variables are passed to subtemplates');
	}

	public function testRenderLayout()
	{
		file_put_contents($this->file, 'Hello <?=$_content?>!');
		file_put_contents($this->file2, '<?=$hello?>');

		$view = new PhpRenderer();
		$view->setLayout($this->file);
		$this->assertEquals('Hello John!', $view->render($this->file2, ['hello' => 'John']));

		$this->assertEquals('Hello John!', $view->render($this->file2, ['hello' => 'John']), 'Re-rendering keeps the layout settings');
	}

	public function testRenderLayoutAndRecursive()
	{
		file_put_contents($this->file, 'Hello <?=$this->render("'.$this->file2.'")?>!');
		file_put_contents($this->file2, '<?=isset($hello)?$hello:""?>');
		file_put_contents($this->file3, '<html><?=$_content?></html>');

		$view = new PhpRenderer();
		$view->setGlobals(['hello' => 'World']);
		$view->setLayout($this->file3);

		$this->assertEquals('<html>Hello World!</html>', $view->render($this->file));
	}

	public function testRenderLayoutRendersItself()
	{
		file_put_contents($this->file, 'Hello world');

		$view = new PhpRenderer();
		$view->setLayout($this->file);

		$this->assertEquals('Hello world', $view->render($this->file));
	}

	public function testRenderCustomLayout()
	{
		file_put_contents($this->file, '<?$this->setLayout("'.$this->file2.'")?>Hello world');
		file_put_contents($this->file2, '<html><?=$_content?></html>');

		$view = new PhpRenderer();

		$this->assertEquals('<html>Hello world</html>', $view->render($this->file));
	}

	public function testRenderCustomLayoutDoesntAffectDefaultLayout()
	{
		file_put_contents($this->file, '<?$this->setLayout("'.$this->file2.'")?>Hello world');
		file_put_contents($this->file2, '<html><?=$_content?></html>');
		file_put_contents($this->file3, 'Hello world');
		file_put_contents($this->file4, '<b><?=$_content?></b>');

		$view = new PhpRenderer();
		$view->setLayout($this->file4);
		$this->assertEquals('<html>Hello world</html>', $view->render($this->file));
		$this->assertEquals('<b>Hello world</b>', $view->render($this->file3));
	}

	public function testPassVariablesToLayout()
	{
		file_put_contents($this->file, '<? $this->setGlobal("title","foobar")?>Hello world');
		file_put_contents($this->file2, '<html><?=$title?> <?=$_content?></html>');

		$view = new PhpRenderer();
		$view->setLayout($this->file2);
		$this->assertEquals('<html>foobar Hello world</html>', $view->render($this->file));
	}
}