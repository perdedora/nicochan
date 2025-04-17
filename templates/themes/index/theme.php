<?php

require_once 'info.php';

function index_build($action, $settings)
{
    $builder = new IndexBuilder();
    $builder->build($action, $settings);
}

class IndexBuilder
{
    private $stats = [];
    private $settings;

    public function build($action, $settings)
    {
        $this->settings = $settings;

        if ($action === 'all') {
            $this->copyBaseCSS();
        }

        if (in_array($action, ['all', 'news', 'post', 'post-thread', 'post-delete'])) {
            $this->generateHomepage();
        }
    }

    private function copyBaseCSS()
    {
        global $config;
        copy('templates/themes/index/' . $this->settings['basecss'], $config['dir']['home'] . $this->settings['css']);
    }

    private function generateHomepage()
    {
        global $config;
        $homepageContent = $this->homepage();
        file_write($config['dir']['home'] . $this->settings['html'], $homepageContent);
    }

    private function homepage()
    {
        global $config;

        $this->populateStats();
        $news = $this->getNews();

        return Element('themes/index/index.html', [
            'settings' => $this->settings,
            'config' => $config,
            'boardlist' => createBoardlist(),
            'stats' => $this->stats,
            'news' => $news,
        ]);
    }

    private function populateStats()
    {
        global $config;

        $boards = listBoards();

        if ($config['cache']['enabled']) {
            $this->stats = Cache::get('stats_homepage') ?: [];
        }

        if (empty($this->stats)) {
            $this->getTotalPosts($boards);
            $this->getUniquePosters($boards);
            $this->getActiveContent($boards);
            $this->getTotalBans();
            $this->getBoardsList($boards);
            $this->stats['update'] = twig_strftime_filter(time(), 'dd/MM/yyyy HH:mm:ss');

            Cache::set('stats_homepage', $this->stats, 3600);
        }
    }

    private function getTotalPosts($boards)
    {
        $query = 'SELECT SUM(`top`) FROM (';
        foreach ($boards as $_board) {
            $query .= sprintf("SELECT MAX(`id`) AS `top` FROM ``posts_%s`` WHERE `shadow` = 0 UNION ALL ", $_board['uri']);
        }
        $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
        $result = query($query) or error(db_error());
        $this->stats['total_posts'] = number_format($result->fetchColumn());
    }

    private function getUniquePosters($boards)
    {
        $query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (';
        foreach ($boards as $_board) {
            $query .= sprintf("SELECT `ip` FROM ``posts_%s`` WHERE `shadow` = 0 UNION ALL ", $_board['uri']);
        }
        $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
        $result = query($query) or error(db_error());
        $this->stats['unique_posters'] = number_format($result->fetchColumn());
    }

    private function getActiveContent($boards)
    {
        $query = 'SELECT `files` FROM (';
        foreach ($boards as $_board) {
            $query .= sprintf("SELECT `files` FROM ``posts_%s`` WHERE `num_files` > 0 AND `shadow` = 0 UNION ALL ", $_board['uri']);
        }
        $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
        $result = query($query) or error(db_error());

        $files = $result->fetchAll(PDO::FETCH_ASSOC);
        $totalFiles = 0;
        $activeContent = 0;

        foreach ($files as $file) {
            $fileJson = $file['files'];
            if (is_string($fileJson)) {
                foreach (json_decode($fileJson) as $f) {
                    if (isset($f->size) && isset($f->file) && $f->file !== 'deleted') {
                        $totalFiles++;
                        $activeContent += $f->size;
                    }
                }
            }
        }

        $this->stats['total_files'] = number_format($totalFiles);
        $this->stats['active_content'] = format_bytes($activeContent);
    }

    private function getTotalBans()
    {
        $query = query("SELECT COUNT(1) FROM ``bans`` WHERE `expires` > UNIX_TIMESTAMP() OR `expires` IS NULL");
        $this->stats['total_bans'] = $query->fetchColumn();
    }

    private function getBoardsList($boards)
    {
        $boardList = [];

        foreach ($boards as $board) {
            if (isset($board['uri'])) {
                $boardInfo = getBoardInfo($board['uri']);

                if ($boardInfo) {
                    $boardList[] = [
                        'title' => $boardInfo['title'],
                        'uri' => $boardInfo['uri'],
                    ];
                }
            }
        }

        $boardList[] = ['title' => 'Overboard', 'uri' => 'overboard'];
        $this->stats['boards'] = $boardList;
    }

    private function getNews()
    {
        $limit = $this->settings['no_recent'] ? ' LIMIT ' . (int)$this->settings['no_recent'] : '';
        $query = query("SELECT * FROM ``news`` ORDER BY `time` DESC $limit") or error(db_error());
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}
