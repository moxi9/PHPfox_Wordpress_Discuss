<?php

namespace Core;

new Route\Group('/wordpress-discuss', function() {

	new Route('/latest', function (Controller $controller) {
		$total = 0;
		$blogs = '<ul>';
		$xml = simplexml_load_string(file_get_contents(setting('pf_wp_d_rss')));
		if (is_object($xml) && isset($xml->channel) && $xml->channel instanceof \SimpleXMLElement) {
			// d($xml->channel->item); exit;
			//if (isset($xml->channel->item) && is_array($xml->channel->item)) {
				foreach ($xml->channel->item as $item) {
					$time = strtotime($item->pubDate);
					$old = strtotime('-7 days');

					if ($time <= $old) {
						continue;
					}

					$total++;
					$blogs .= '<li style="padding-bottom: 10px;"><a href="' . $item->link . '">' . $item->title . '</a><div class="extra_info">' . \Phpfox::getLib('date')->convertTime($time) . '</div></li>';
				}
			// }
		}
		$blogs .= '</ul>';

		if (!$total) {
			return;
		}

		echo $blogs;
	});

	new Route('/admincp', function(Controller $controller) {
		if (!\Phpfox::isAdmin()) {
			return '';
		}

		$token = setting('pf_wp_d_token');
		if (!$token) {
			$token = md5(uniqid());
			$app = (new App())->get('PHPfox_Wordpress_Discuss');
			$setting = new Setting\Service($app);
			$setting->save([
				'pf_wp_d_token' => $token
			]);
		}

		return $controller->render('admincp.html', [
			'hookUrl' => $controller->url->make('/wordpress-discuss/new-post/' . $token)
		]);
	});

	new Route('/new-post/:token', function(Controller $controller, $token) {
		$tokenSetting = setting('pf_wp_d_token');
		if (empty($tokenSetting)) {
			throw error('Token has not been created yet.');
		}

		if ($token != $tokenSetting) {
			throw error('Token miss-match');
		}

		if (!isset($_REQUEST['post_title'])) {
			throw error('Missing post title.');
		}

		if ($_REQUEST['post_status'] != 'publish') {
			throw error('Post is not published.');
		}

		$content = setting('pf_wp_d_mesage');
		$content = str_replace('{{ title }}', '[link="' . $_REQUEST['post_url'] . '"]' . $_REQUEST['post_title'] . '[/link]', $content);

		\Forum_Service_Thread_Process::instance()->add([
			'forum_id' => setting('pf_wp_d_id'),
			'title' => $_REQUEST['post_title'],
			'time_stamp' => PHPFOX_TIME,
			'time_update' => PHPFOX_TIME,
			'text' => $content
		], false, [
			'user_id' => setting('pf_wp_d_user_id'),
			'time_stamp' => PHPFOX_TIME,
			'time_update' => PHPFOX_TIME,
		]);
	});
});

return function(App\Object $App, \Twig_Environment $View) {
	new Event('lib_module_get_blocks', function(\Phpfox_Module $object) use($View) {

		if (!setting('pf_wp_d_rss')) {
			return false;
		}


		$object->block('core.index-member', 1, $View->render('@PHPfox_Wordpress_Discuss/block.html', [

		]));
	});
};