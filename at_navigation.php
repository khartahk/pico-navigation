<?php

/**
 * navigation plugin which generates a better configurable navigation with endless children navigations
 *
 * @author Ahmet Topal
 * @link http://ahmet-topal.com
 * @license http://opensource.org/licenses/MIT
 */
final class AT_Navigation extends AbstractPicoPlugin
{
	##
	# VARS
	##
	protected $enabled = true;
	protected $dependsOn = array();
	protected $navigation = array();

	##
	# HOOKS
	##

	public function onPagesLoaded(
			array &$pages,
			array &$currentPage = null,
			array &$previousPage = null,
			array &$nextPage = null
	) {
		$navigation = array();

		foreach ($pages as $page)
		{
			if (!$this->at_exclude($page))
			{
				$_split = explode('/', substr($page['url'], strlen($this->config['base_url'])+1));
				$navigation = array_merge_recursive($navigation, $this->at_recursive($_split, $page, $currentPage));
			}
		}

		array_multisort($navigation);
		$this->navigation = $navigation;
	}

	public function onConfigLoaded(array &$config)
	{
		$this->config = $config;

		// default id
		if (!isset($this->config['at_navigation']['id'])) { $this->config['at_navigation']['id'] = 'at-navigation'; }

		// default classes
		if (!isset($this->config['at_navigation']['class'])) { $this->config['at_navigation']['class'] = 'at-navigation'; }
		if (!isset($this->config['at_navigation']['class_li'])) { $this->config['at_navigation']['class_li'] = 'li-item'; }
		if (!isset($this->config['at_navigation']['class_a'])) { $this->config['at_navigation']['class_a'] = 'a-item'; }

		// default excludes
		$this->config['at_navigation']['exclude'] = array_merge_recursive(
			array('single' => array(), 'folder' => array()),
			isset($this->config['at_navigation']['exclude']) ? $this->config['at_navigation']['exclude'] : array()
		);
	}

	public function onPageRendering(Twig_Environment &$twig, array &$twigVariables, &$templateName)
	{
		$twigVariables['at_navigation']['navigation'] = $this->at_build_navigation($this->navigation, true);
	}

	##
	# HELPER
	##

	private function at_build_navigation($navigation = array(), $start = false)
	{
		$id = $start ? $this->config['at_navigation']['id'] : '';
		$class = $start ? $this->config['at_navigation']['class'] : '';
		$class_li = $this->config['at_navigation']['class_li'];
		$class_a = $this->config['at_navigation']['class_a'];
		$child = '';
		$ul = $start ? '<ul id="%s" class="%s">%s</ul>' : '<ul>%s</ul>';

		if (isset($navigation['_child']))
		{
			$_child = $navigation['_child'];
			array_multisort($_child);

			foreach ($_child as $c)
			{
				$child .= $this->at_build_navigation($c);
			}

			$child = $start ? sprintf($ul, $id, $class, $child) : sprintf($ul, $child);
		}

		$li = isset($navigation['title'])
			? sprintf(
				'<li class="%1$s %5$s"><a href="%2$s" class="%1$s %6$s" title="%3$s">%3$s</a>%4$s</li>',
				$navigation['class'],
				$navigation['url'],
				$navigation['title'],
				$child,
				$class_li,
				$class_a
			)
			: $child;

		return $li;
	}

	private function at_exclude($page)
	{
		$exclude = $this->config['at_navigation']['exclude'];

		$url = substr($page['url'], strlen($this->config['base_url']));
		$url = (substr($url, -1) == '/') ? $url : $url.'/';

		foreach ($exclude['single'] as $s)
		{
			$s = (substr($s, -1*strlen('index')) == 'index') ? substr($s, 0, -1*strlen('index')) : $s;
			$s = (substr($s, -1) == '/') ? $s : $s.'/';

			if ($url == $s)
			{
				return true;
			}
		}

		foreach ($exclude['folder'] as $f)
		{
			$f = (substr($f, -1) == '/') ? $f : $f.'/';
			$is_index = ( $f == '' || $f == '/' ) ? true : false;

			if ( substr($url, 0, strlen($f)) == $f )
			{
				return true;
			}
		}

		return false;
	}

	private function at_recursive($split = array(), $page = array(), $current_page = array())
	{
		$activeClass = (isset($this->config['at_navigation']['activeClass'])) ? $this->config['at_navigation']['activeClass'] : 'is-active';
		if (count($split) == 1)
		{
			$is_index = ($split[0] == '') ? true : false;
			$ret = array(
				'title'      => $page['title'],
				'url'      => $page['url'],
				'class'      => ($page['url'] == $current_page['url']) ? $activeClass : ''
			);

			$split0 = ($split[0] == '') ? '_index' : $split[0];
			return array('_child' => array($split0 => $ret));
			return $is_index ? $ret : array('_child' => array($split[0] => $ret));
		}
		else
		{
			if ($split[1] == '')
			{
				array_pop($split);
				return $this->at_recursive($split, $page, $current_page);
			}

			$first = array_shift($split);
			return array('_child' => array($first => $this->at_recursive($split, $page, $current_page)));
		}
	}
}
