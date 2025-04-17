<?php
 require 'inc/bootstrap.php';
 checkBan();

 die(
    Element('page.html', array(
      'title' => _('You are not banned!'),
      'config' => $config,
      'nojavascript' => true,
      'boardlist' => createBoardlist(FALSE),
      'body' => Element('notbanned.html', array(
	      'text_body' => _('Congratulations for not being terrible!'),
	      'text_h2' => _('You are not banned!'),
		)
	))
	));


?>
