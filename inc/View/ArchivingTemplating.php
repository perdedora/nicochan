<?php

namespace Vichan\View;

use Vichan\Data\ArchiveQueries;

class ArchiveTemplating {
    private array $config;
    private ArchiveQueries $db;

    public function __construct(array $config, ArchiveQueries $archiveQueries) {
        $this->config = $config;

        $this->db = $archiveQueries;
    }

     /**
     * Archive list markup.
     *
     * @param string $body The body content to transform
     * @return string The transformed body
     */
    public static function archiveListMarkup(string $body): string {

        $body = \str_replace("\r", '', $body);
        $body = \utf8tohtml($body);
        $body = \preg_replace("/^\s*&gt;.*$/m", '<span class="quote">$0</span>', $body);
        $body = \preg_replace("/^\s*&lt;.*$/m", '<span class="rquote">$0</span>', $body);
        $body = \str_replace("\t", '		', $body);

        return $body;
    }

    /**
     * Create a snippet from the body and subject.
     *
     * @param string $body The body content to generate the snippet from
     * @param int $maxSnippetLength Max length of snippet
     * @param string|null $subject The subject of the snippet
     * @return string The generated snippet
     */
    public static function createSnippet(string $body, int $maxSnippetLength, ?string $subject = null): string {
        $subject_length = $subject ? \strlen($subject) : 0;
        $max_snippet_length = $maxSnippetLength - $subject_length;

        $snippet = \strtok($body, "\r\n");
        $snippet = \substr($snippet, 0, $max_snippet_length);
        $snippet = self::archiveListMarkup($snippet);

        return $subject ? "<b>{$subject}</b> {$snippet}" : $snippet;
    }

    /**
     * Creates archive index list and writes to disk.
     * 
     * @return bool true if it was successfull and false it archiving is disable.
     */
    public function buildArchiveIndex(): bool {
        global $board;

        if (!$this->config['archive']['threads']) {
            return false;
        }

        $filePageFormat = $this->config['remove_ext']
            ? $this->config['file_page_no_ext']
            : $this->config['file_page'];

        $threadsPerPage = $this->config['archive']['threads_per_page'] ?? 50;

        $totalThreads = $this->db->getArchiveCount($this->config['archive']['lifetime'], $board['uri']);
        $totalPages = max(1, (int) \ceil($totalThreads / $threadsPerPage));

        for ($currentPage = 1; $currentPage <= $totalPages; $currentPage++) {
            $offset = ($currentPage - 1) * $threadsPerPage;

            $archiveList = $this->db->getArchiveList(
                $this->config['archive']['lifetime'],
                $board['uri'],
                $offset,
                $threadsPerPage
            );

            foreach ($archiveList as &$thread) {
                $thread['archived_url'] = $this->config['dir']['res'] . \sprintf($filePageFormat, $thread['thread_id']);
            }

            $pagination = [
                'current' => $currentPage,
                'total' => $totalPages,
            ];

            $title = \sprintf(
                \_('Archived') . ' %s: ' . $this->config['board_abbreviation'],
                \_('threads'),
                $board['uri']
            );

            $archivePage = \Element('page.html', [
                'config' => $this->config,
                'mod' => false,
                'hide_dashboard_link' => true,
                'boardlist' => \createBoardlist(false),
                'title' => $title,
                'subtitle' => '',
                'body' => \Element('mod/archive_list.html', [
                    'config' => $this->config,
                    'thread_count' => $totalThreads,
                    'board' => $board,
                    'archive' => $archiveList,
                    'pagination' => $pagination,
                ])
            ]);

            $filename = $currentPage === 1
                ? $this->config['file_index_no_ext']
                : $currentPage;

            $outputPath = $this->config['dir']['home']
                . $board['dir']
                . $this->config['dir']['archive']
                . $filename;

            \file_write($outputPath, $archivePage);
        }

        return true;
    }
}
