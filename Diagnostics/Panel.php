<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008, 2012 Filip Procházka (filip.prochazka@kdyby.org)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Extension\Redis\Diagnostics;

use Kdyby;
use Kdyby\Extension\Redis\RedisClientException;
use Nette;
use Nette\Diagnostics\Debugger;
use Nette\Utils\PhpGenerator as Code;



/**
 * @author Filip Procházka <filip.prochazka@kdyby.org>
 */
class Panel extends Nette\Object implements Nette\Diagnostics\IBarPanel
{

	const TIMER_NAME = 'redis-client-timer';

	/**
	 * @var int
	 */
	private $totalTime = 0;

	/**
	 * @var int
	 */
	private $queries = 0;

	/**
	 * @var int
	 */
	private $transfered = 0;

	/**
	 * @var array
	 */
	private $errors = array();



	/**
	 */
	public function begin()
	{
		Debugger::timer(self::TIMER_NAME);
	}



	/**
	 * @param \Exception $e
	 */
	public function error(\Exception $e)
	{
		$this->errors[] = $e;
	}



	/**
	 * @param string $size
	 */
	public function dataSize($size)
	{
		$this->transfered += $size;
	}



	/**
	 */
	public function end()
	{
		$this->totalTime += Debugger::timer(self::TIMER_NAME);
		$this->queries += 1;
	}



	/**
	 * Renders HTML code for custom tab.
	 * @return string
	 */
	public function getTab()
	{
		return '<span title="Redis Storage">' .
			'<img alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAuIwAALiMBeKU/dgAAAAd0SU1FB9wHCxYIODR5wLEAAAMUSURBVDjLPZO7bxxVGMV/3517Z2Z37bU39mLiBCURwcRBRIIEwqugp4GCBkUREo+CDqUIFClpgIII8RckBUEoEg1lQKIkiQRIeUgUedlO7PUjXts7cx/z0Tic6qdzpHOqIwC3jh7kyI077LIARlVflCw7i8g7wFWN8WsRuQKEIzfuKLuSJ3D7pedNHAymJHcvi3VfSJa93XnjTVrHXyUsPGDj50uIyN9G5Csj8ltomvX5m3ej3V093GwN38oPHPjUjHdfR5VmOCQ/PKfdd9+X4a+/kE1MUG1sHNtO6VLb2tulMT/cnD9wRW4fPXhGVU/j3LHZb85TzM1Dk3Tn2p+y+PlntE68ws7162wWBRsxcnVjTZ/rjMkL411KY27Z+1X1bd852llGWn5E6IxBShIW7iPOkZaXaR8/wfCfvyiM4WCrLVN5rlFVlur6iFzYO6ktm2kvszIz1qYz3Sf6QPvka/Q+/ATbn+HhubPs/PE7ajNiozyOkZXa61aMkn081Z2uUnPycYys1Z5quEnbe3SwQnhwD20aNi//hKbEwAfujkYMfCA0KrOtMsjVuf11O8vyh3XNUlXTqJKJMFMU7G2VOGtZG424P6oYpQZQ+nnOvlaJgsqF2V7Tz3PZVxYYEZaqihUfiE1DJkJmDD4ljAiTzrK/LLHG8KiqWaprbC5yZ+D9oRXvmclzni4LnioKlmvPagg0qnSdZV+rRcsYBrVnoapIqpSZwR4e66xuxXhozQeWvWe5rpkpS6ZzR7/Iiao4MTwOgX+rbXzT0LEZPefYk+fI5WemdKYotMwyRinJug+sh4ARoZ/n5MawFgLDEOk6yx7nGHeZ+gZW6lrsVkzXtuPO8XFrmXROZ8tS+kXOqg88rGuiKhPW8mynTdtmVCnp4qiWzRhJqvfk4mxvTlXfUzhjRPqtLGPSWe1ZJwpEbSiNYTMlXfVedmIiqnqB7wR+FICLsz0HTKvqB8CXRmTKirAnd1oYIwMftEpJkirA9yJyHlg4tbhe/f/G3SKDalfhI+CciEw8yVT14q734NTienzi/weR/4QEsuMkfwAAAABJRU5ErkJggg==" />' .
			$this->queries . ' queries' .
			($this->errors ? ' / ' . count($this->errors) . ' errors' : '') .
			($this->transfered ? ' / ' . number_format($this->transfered / 1000000, 2, '.', ' ') . ' MB' : '') .
			($this->queries ? ' / ' . sprintf('%0.1f', $this->totalTime * 1000) . ' ms' : '') .
			'</span>';
	}



	/**
	 * Renders HTML code for custom panel.
	 * @return string
	 */
	public function getPanel()
	{
	}



	/**
	 * @param \Exception|RedisClientException $e
	 *
	 * @return array
	 */
	public static function renderException($e)
	{
		if ($e instanceof RedisClientException) {
			$panel = NULL;
			if ($e->request) {
				$panel .= '<h3>Redis Request</h3>' .
					'<pre class="nette-dump"><span class="php-string">' .
					nl2br(Nette\Templating\Helpers::escapeHtml(implode(' ', $e->request))) .
					'</span></pre>';
			}
			if ($e->response) {
				$response = Code\Helpers::dump($e->response);
				$panel .= '<h3>Redis Response (' . strlen($e->response) . ')</h3>' .
					'<pre class="nette-dump"><span class="php-string">' .
					Nette\Templating\Helpers::escapeHtml($response) .
					'</span></pre>';
			}

			if ($panel !== NULL) {
				$panel = array(
					'tab' => 'Redis Response',
					'panel' => $panel
				);
			}

			return $panel;
		}
	}



	/**
	 * @return \Kdyby\Extension\Redis\Diagnostics\Panel
	 */
	public static function register()
	{
		Debugger::$blueScreen->addPanel(array($panel = new static(), 'renderException'));
		Debugger::$bar->addPanel($panel);
		return $panel;
	}

}
