<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

/*
	joaoptm78@gmail.com
	http://www.php.net/manual/en/function.filesize.php#100097
*/
function format_bytes($size) {
	$units = array(' B', ' KB', ' MB', ' GB', ' TB');
	for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
	return round($size, 2).$units[$i];
}

function doBoardListPart($list, $root, &$boards) {
	global $config;

	$body = '';
	foreach ($list as $key => $board) {
		if (is_array($board))
			$body .= ' <span class="sub" data-description="' . $key . '">[' . doBoardListPart($board, $root, $boards) . ']</span> ';
		else {
			if (gettype($key) == 'string') {
				$body .= ' <a href="' . $board . '">' . $key . '</a> /';
			} else {
				$title = '';
				if (isset ($boards[$board])) {
					$title = ' title="'.$boards[$board].'"';
				}

				$body .= ' <a data-isboard="true" href="' . $root . $board . '/' . '"'.$title.'>' . $board . '</a> /';
			}
		}
	}
	$body = preg_replace('/\/$/', '', $body);

	return $body;
}

function createBoardlist($mod=false) {
	global $config;

	if (!isset($config['boards'])) return array('top'=>'','bottom'=>'');

	$xboards = listBoards();
	$boards = array();
	foreach ($xboards as $val) {
		$boards[$val['uri']] = $val['title'];
	}

	$body = doBoardListPart($config['boards'], $mod?'?/':$config['root'], $boards);

	if ($config['boardlist_wrap_bracket'] && !preg_match('/\] $/', $body))
		$body = '[' . $body . ']';

	$body = trim($body);

	return array(
		'top' => '<div class="boardlist">' . $body . '</div>',
		'bottom' => '<div class="boardlist bottom">' . $body . '</div>'
	);
}

function error($message, $priority = true, $debug_stuff = []) {
	global $board, $mod, $config, $db_error;

	if ($config['syslog'] && $priority !== false) {
		// Use LOG_NOTICE instead of LOG_ERR or LOG_WARNING because most error message are not significant.
		_syslog($priority !== true ? $priority : LOG_NOTICE, $message);
	}

	if (defined('STDIN')) {
		// Running from CLI
		echo "Error: $message\n";
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		die();
	}

	if (isset($_POST['json_response'])) {
		header('Content-Type: text/json; charset=utf-8');
		die(json_encode([
			'error' => $message
		]));
	} else {
		header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
	}

	if ($config['debug']) {
		if (isset($db_error)) {
			$debug_stuff = array_combine(['SQLSTATE', 'Error code', 'Error message'], $db_error);
		}

		$debug_stuff['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}

	die(Element('page.html', [
		'config' => $config,
		'title' => _('Error'),
		'subtitle' => _('An error has occured.'),
		'body' => Element('error.html', [
			'config' => $config,
			'message' => $message,
			'mod' => $mod,
			'board' => isset($board) ? $board : false,
			'debug' => $config['debug'] ? str_replace("\n", '&#10;', utf8tohtml(print_r($debug_stuff, true))) : null
		])
	]));
}

function message($message, $title = '',  $subtitle = '') {
	global $board, $mod, $config;

	if (isset($_POST['json_response'])) {
		header('Content-Type: text/json; charset=utf-8');
		die(json_encode(array(
			'error' => $message
		)));
	}

	die(Element('page.html', array(
		'config' => $config,
		'title' => $title,
		'subtitle' => $subtitle,
		'body' => Element('error.html', array(
			'config' => $config,
			'message' => $message,
			'mod' => $mod,
			'board' => isset($board) ? $board : false
		))
	)));
}

function pm_snippet($body, $len=null) {
	global $config;

	if (!isset($len))
		$len = &$config['mod']['snippet_length'];

	// Replace line breaks with some whitespace
	$body = preg_replace('@<br/?>@i', '  ', $body);

	// Strip tags
	$body = strip_tags($body);

	// Unescape HTML characters, to avoid splitting them in half
	$body = html_entity_decode($body, ENT_COMPAT, 'UTF-8');

	// calculate strlen() so we can add "..." after if needed
	$strlen = mb_strlen($body);

	$body = mb_substr($body, 0, $len);

	// Re-escape the characters.
	return '<em>' . utf8tohtml($body) . ($strlen > $len ? '&hellip;' : '') . '</em>';
}

function capcode($cap) {
	global $config;

	if (!$cap)
		return false;

	$capcode = array();
	if (isset($config['custom_capcode'][$cap])) {
		if (is_array($config['custom_capcode'][$cap])) {
			$capcode['cap'] = sprintf($config['custom_capcode'][$cap][0], $cap);
			if (isset($config['custom_capcode'][$cap][1]))
				$capcode['name'] = $config['custom_capcode'][$cap][1];
			if (isset($config['custom_capcode'][$cap][2]))
				$capcode['trip'] = $config['custom_capcode'][$cap][2];
		} else {
			$capcode['cap'] = sprintf($config['custom_capcode'][$cap], $cap);
		}
	} else {
		$capcode['cap'] = sprintf($config['capcode'], $cap);
	}

	return $capcode;
}

function truncate($body, $url, $max_lines = false, $max_chars = false) {
	global $config;

	if ($max_lines === false)
		$max_lines = $config['body_truncate'];
	if ($max_chars === false)
		$max_chars = $config['body_truncate_char'];

	// We don't want to risk truncating in the middle of an HTML comment.
	// It's easiest just to remove them all first.
	$body = preg_replace('/<!--.*?-->/s', '', $body);

	$original_body = $body;

	$lines = substr_count($body, '<br/>');

	// Limit line count
	if ($lines > $max_lines) {
		if (preg_match('/(((.*?)<br\/>){' . $max_lines . '})/', $body, $m))
			$body = $m[0];
	}

	$body = mb_substr($body, 0, $max_chars);

	if ($body != $original_body) {
		// Remove any corrupt tags at the end
		$body = preg_replace('/<([\w]+)?([^>]*)?$/', '', $body);

		// Open tags
		if (preg_match_all('/<([\w]+)[^>]*>/', $body, $open_tags)) {

			$tags = array();
			for ($x=0;$x<count($open_tags[0]);$x++) {
				if (!preg_match('/\/(\s+)?>$/', $open_tags[0][$x]))
					$tags[] = $open_tags[1][$x];
			}

			// List successfully closed tags
			if (preg_match_all('/(<\/([\w]+))>/', $body, $closed_tags)) {
				for ($x=0;$x<count($closed_tags[0]);$x++) {
					unset($tags[array_search($closed_tags[2][$x], $tags)]);
				}
			}

			// remove broken HTML entity at the end (if existent)
			$body = preg_replace('/&[^;]+$/', '', $body);

			$tags_no_close_needed = array("colgroup", "dd", "dt", "li", "optgroup", "option", "p", "tbody", "td", "tfoot", "th", "thead", "tr", "br", "img");

			// Close any open tags
			foreach ($tags as &$tag) {
				if (!in_array($tag, $tags_no_close_needed))
					$body .= "</{$tag}>";
			}
		} else {
			// remove broken HTML entity at the end (if existent)
			$body = preg_replace('/&[^;]*$/', '', $body);
		}

		$body .= '<span class="toolong">'.sprintf(_('Post too long. Click <a href="%s">here</a> to view the full text.'), $url).'</span>';
	}

	return $body;
}

function bidi_cleanup($data) {
	// Closes all embedded RTL and LTR unicode formatting blocks in a string so that
	// it can be used inside another without controlling its direction.

	$explicits	= '\xE2\x80\xAA|\xE2\x80\xAB|\xE2\x80\xAD|\xE2\x80\xAE';
	$pdf		= '\xE2\x80\xAC';

	preg_match_all("!$explicits!",	$data, $m1, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
	preg_match_all("!$pdf!", 	$data, $m2, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

	if (count($m1) || count($m2)){

		$p = array();
		foreach ($m1 as $m){ $p[$m[0][1]] = 'push'; }
		foreach ($m2 as $m){ $p[$m[0][1]] = 'pop'; }
		ksort($p);

		$offset = 0;
		$stack = 0;
		foreach ($p as $pos => $type){

			if ($type == 'push'){
				$stack++;
			}else{
				if ($stack){
					$stack--;
				}else{
					# we have a pop without a push - remove it
					$data = substr($data, 0, $pos-$offset)
						.substr($data, $pos+3-$offset);
					$offset += 3;
				}
			}
		}

		# now add some pops if your stack is bigger than 0
		for ($i=0; $i<$stack; $i++){
			$data .= "\xE2\x80\xAC";
		}

		return $data;
	}

	return $data;
}

function secure_link_confirm($text, $title, $confirm_message, $href, $data_action, $append_title = false) {
    $secure_url = htmlspecialchars('?/' . $href . '/' . make_secure_link_token($href), ENT_QUOTES, 'UTF-8');

	$title = htmlentities($title);

	$confirm_message = htmlentities($confirm_message);
	$data_action = htmlentities($data_action);
	$innerhtml = $append_title ? "{$text} - {$title}" : $text;

	return "<a title='{$title}' data-href='{$secure_url}' data-action='{$data_action}' href='?/{$href}' data-confirm='{$confirm_message}'>{$innerhtml}</a>";
}

function secure_link($href) {
	return $href . '/' . make_secure_link_token($href);
}

abstract class AbstractPost
{
    protected array $config;
    public string $board;
    public array $full_board;
    public string $root;
    public mixed $mod;
    public int $id;
    public ?int $thread;
    public ?string $subject;
    public ?string $email;
    public ?string $name;
    public ?string $trip;
    public ?string $capcode;
    public string $body;
    public string $body_nomarkup;
    public int $time;
    public int $bump;
    public mixed $files;
    public int $num_files;
    public ?string $filehash;
    public ?string $password;
    public string $ip_human_readable;
    public string $ip;
    public string $cookie;
    public bool $shadow;
	public ?bool $archive;
    public ?string $embed;
    public ?string $slug;
    public ?string $flag_iso;
    public ?string $flag_ext;
    public ?string $embed_url;
    public ?string $embed_title;
	public ?string $embed_thumbnail;
    public ?array $modifiers;

    public function __construct(array $config, array $post, string $root = '', mixed $mod = false)
    {
        $this->config = $config;
        $this->root = empty($root) ? $this->config['root'] : $root;
        $this->mod = $mod;

        $this->initialize($post);
    }

    protected function initialize(array $post): void
    {
        foreach ($post as $key => $value) {
            $this->{$key} = $value;
        }

        $this->full_board['uri'] = $this->board;
        $this->full_board['dir'] = sprintf($this->config['board_path'], $this->board);
        $this->ip_human_readable = getHumanReadableIP($this->ip);
        $this->files = is_string($this->files) ? json_decode($this->files) : $this->files;
        $this->subject = utf8tohtml($this->subject);

        if ($this->embed) {
            $url = json_decode($this->embed);
            $this->embed_url = $url->url;
            $this->embed_title = $url->title;
			$this->embed_thumbnail = isset($url->thumbnail) ? $url->thumbnail : null;
            $this->embed_html($url);
        }

        $this->modifiers = extract_modifiers($this->body_nomarkup);

        if ($this->config['always_regenerate_markup']) {
            $this->body = $this->body_nomarkup;
            markup($this->body);
        }

        if ($this->mod) {
            $this->fixInternalLinks();
        }
    }

    protected function fixInternalLinks(): void
    {
        $this->body = preg_replace(
            '/<a\s*((?:[a-zA-Z-]+="[^"]*"\s*)*)href="'
            . preg_quote($this->config['root'], '/')
            . '('
            . sprintf(preg_quote($this->config['board_path'], '/'), $this->config['board_regex'])
            . '([^"]+))"/u',
            '<a $1href="?/$2"',
            $this->body
        );
    }

    public function link(string $pre = '', string|bool $page = false, bool $no_hash = false): string
    {
        $link = $this->root . $this->full_board['dir'] . $this->config['dir']['res'] . link_for((array)$this, $page == '50');

        if (!$no_hash) {
            $link .= '#' . $pre . $this->id;
        }

        return $link;
    }

    public function embed_html(object $link): void
    {
        foreach ($this->config['embeds'] as $embed) {
            if ($html = preg_replace($embed['regex'], $embed['html'], $link->url)) {
                if ($html === $link->url) {
                    continue;
                } // Nope

                $html = str_replace('%%VIDEO_NAME%%', htmlspecialchars(twig_truncate_filter($link->title, 44)), $html);
                $html = str_replace('%%VIDEO_FULLNAME%%', htmlspecialchars($link->title), $html);
                $html = str_replace('%%tb_width%%', $this->config['embed_width'], $html);
                $html = str_replace('%%tb_height%%', $this->config['embed_height'], $html);

				$thumbnail = isset($link->thumbnail) && $link->thumbnail
					? htmlspecialchars($link->thumbnail)
					: $this->config['image_deleted'];

				$html = str_replace('%%THUMBNAIL_URL%%', $thumbnail, $html);

                $this->embed = $html;
				break;
            }

        	$this->embed = 'Embedding error.';
        }

    }

    abstract public function build(bool $index = false);
}

class Post extends AbstractPost
{
    public function build(bool $index = false): string
    {
        return Element('post_reply.html', [
            'config' => $this->config,
            'board' => $this->full_board,
            'post' => &$this,
            'index' => $index,
            'mod' => $this->mod,
			'pm' => $this->mod ?: create_pm_header()
        ]);
    }
}

class Thread extends AbstractPost
{
    public array $posts = [];
    public bool $sticky;
    public bool $locked;
    public bool $cycle;
    public bool $sage;
    public bool $hideid;
    public int $omitted = 0;
    public int $omitted_images = 0;
    public int $thread_id;
    public int $reply_count;
	public int $image_count;
    public string $link;
    public string $file;
    public string $orig_file;
    public string $pubdate;
    public int $images;
    public int $replies;
    public bool $hr;

    public function __construct(array $config, array $post, string $root = '', mixed $mod = false, bool $hr = true)
    {
        parent::__construct($config, $post, $root, $mod);
        $this->hr = $hr;
    }

    public function add(Post $post): void
    {
        $this->posts[] = $post;
    }

    public function postCount(): int
    {
        return count($this->posts) + $this->omitted;
    }

    public function build(bool $index = false, bool $isnoko50 = false): string
    {
        $hasnoko50 = $this->postCount() >= $this->config['noko50_min'];

        event('show-thread', $this);

        if ($isnoko50) {
            $this->fixInlineLinks();
        }

        return Element('post_thread.html', [
            'config' => $this->config,
            'hideposterid' => $this->hideid,
            'board' => $this->full_board,
            'post' => &$this,
            'index' => $index,
            'hasnoko50' => $hasnoko50,
            'isnoko50' => $isnoko50,
            'mod' => $this->mod,
			'pm' => $this->mod ?: create_pm_header()
        ]);
    }

    private function fixInlineLinks(): void
    {
	    if (empty($this->posts) || count($this->posts) < 2) {
        	return;
    	}

    	$thread_num = $this->posts[0]->id;
    	$min_post_num = $this->posts[1]->id;

    	for ($i = 1; $i < count($this->posts); $i++) {
        	$body = $this->posts[$i]->body;

        	if (!is_string($body)) {
            	continue;
        	}

        	preg_match_all(
            	'/(href="\?\/' . preg_quote($this->full_board['dir'], '/') . 'res\/)(\d+)#(\d+)(")/',
            	$body,
            	$matches,
            	PREG_SET_ORDER
        	);

        	$patterns = [];
        	$changes = [];
        	foreach ($matches as $match) {
            	list($fullMatch, $prefix, $pageId, $postId, $suffix) = $match;

            	if ($postId >= $min_post_num || $postId == $thread_num) {
                	$patterns[] = '/' . preg_quote($fullMatch, '/') . '/';
                	$changes[] = $prefix . sprintf($this->config['file_page50'], $pageId) . "#" . $postId . $suffix;
            	}
        	}

        	$this->posts[$i]->body = preg_replace($patterns, $changes, $body);
    	}
	}
}
