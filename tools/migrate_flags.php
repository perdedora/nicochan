<?php
require 'inc/bootstrap.php';

/*

Script to extract flags from posts, insert into the database
and delete from the flag modifiers

Known limitations: it leaves blank lines after removing the flag modifiers
*/


$boards = listBoards();
foreach ($boards as &$board) {
    
    $query = prepare(sprintf('SELECT id, body_nomarkup FROM ``posts_%s`` 
                        WHERE `body_nomarkup` LIKE "%%<tinyboard flag>%%"', 
                    $board['uri']));
	$query->execute() or error(db_error($query));

    while($post = $query->fetch()) {

        if (!empty($post['body_nomarkup']) && isset($post['body_nomarkup'])) {
		    $modifiers = extract_modifiers($post['body_nomarkup']);

            $post['body_nomarkup'] = preg_replace('#<tinyboard flag.*<\/tinyboard>#', '', $post['body_nomarkup']);

            $update_query = prepare(sprintf("UPDATE ``posts_%s`` 
                                            SET `flag_iso` = :flag_iso
                                                ,`flag_ext` = :flag_ext
                                                ,`body_nomarkup` = :body_nomarkup 
                                            WHERE `id` = :id", $board['uri']));
            
		    $update_query->bindValue(':flag_iso', $modifiers['flag']);
		    $update_query->bindValue(':flag_ext', $modifiers['flag alt']);
            $update_query->bindValue(':body_nomarkup', $post['body_nomarkup']);
            $update_query->bindValue(':id', $post['id']);
		    $update_query->execute() or db_error();
            
        }
    }
}