<?php

/**
 * Classes for converting raw phpBB code to XHTML and some helpers
 *
 * This file is a php version of BBCode.py from bbgun made by Joe Friedrichsen <pengi.films@gmail.com>
 */
if (!defined('VERBOSE'))
    define('VERBOSE', false);

/**
 * Convert raw phpBB code to XHTML
 */
class BBCodeConverter {

    private $attachments = array();
    private $attach_idx = 0;
    private $text = null;
    private $fetchimages = false;
    private $bbStrict = false;
    private $ctx;
    private $oMigration;

    /**
     * Constructor.
     */
    function __construct() {

    }

    /**
     * Links the code converter to the zMigration instance
     * in order to access the database for converting attachements
     */
    function setMigration($oMigration) {
	$this->oMigration = $oMigration;
    }

    /**
     * Returns attachments list and also removes them from the instance.
     */
    function dumpAttachments() {
	$a = $this->attachments;
	$this->attachments = array();
	return $a;
    }

    /**
     * Sets $fetchimage property
     * bool
     * Added by asuhan.
     */
    function setFetchImages($fetchimages) {
	$this->fetchimages = $fetchimages;
    }

    /**
     * Sets value of $context variable.
     * string
     * Added by asuhan.
     */
    function setContext($context) {
	$this->ctx = $context;
    }

    /**
     * Find bbCode tags and convert them to xhtml tags
     *
     * Keyword ags:
     * text -- the text to be converted
     *
     * to_xhtml uses several hidden methods to individually handle the
     * bbCode tags in the text. Changing the translation for one tag is quite
     * simple this way. Please see the source code if you'd like to change or
     * add a translation.
     */
    function toXhtml($text, $bbStrict = false) {
	$this->bbStrict = $bbStrict;

	/* Regular expression for start tags */
	$START_TAG = '
                                \[          # all start tags begin with an open square bracket
                                (\w+)       # GROUP: the name of the tag
                                [:=]?       # there may be a colon or equal sign, but only one
                                ([a-zA-Z0-9]{0,10})?    # GROUP: either an attr or hex session id
                                [:=]?       # there may be another single colon or equal sign
                                \/*          # urls have slashes after their \'http:\'
                                "?          # quotes have double quote delims for the speaker\'s name
                                (.*?)       # GROUP: the name, url, or session id
                                "?          # quotes have a closing double quote as well
                                \]          # all start tags finish with a closed square bracket
                                ';

	preg_match_all('/' . $START_TAG . '/xs', $text, $start_tags, PREG_SET_ORDER);

	if (VERBOSE)
	    echo "Retrieved start tags - " . count($start_tags) . " found." . PHP_EOL;

	/* Regular expression for end tags */
	$END_TAG = '
                                \[\/         # all end tags begin with an open square bracket and slash
                                (\w+)       # GROUP: the name of the tag
                                [:=]?       # there may be a single colon or equal sign
                                ([a-zA-Z0-9]{0,10})?    # GROUP: either an attr or hex session id
                                [:=]?       # there may be another singl colon or equal sign
                                (.*?)       # GROUP: the hex session id
                                \]          # all end tags finish with a closed square bracket
                                ';

	preg_match_all('/' . $END_TAG . '/xs', $text, $end_tags, PREG_SET_ORDER);

	if (VERBOSE)
	    echo "Retrieved end tags - " . count($end_tags) . " found." . PHP_EOL;

	/* Capture a pair of tags and GROUP the text between them
	 *    NB: Doesn't find [b]overlapped [i]tag[/b] pairs[/i],
	 *        but will return a matched pair (bold and bold, ital and ital...)
	 */
	$TAG_PAIR = '
                \[           # all start tags begin with an open square bracket
                (?P<tag>\w+) # GROUP and NAME: the name of the tag
                [:=]?        # there may be a colon or equal sign, but only one
                ([a-zA-Z0-9]{0,10})?    # GROUP: either an attr or hex session id
                [:=]?        # there may be a single colon or equal sign
                \/*           # urls have slashes after their \'http:\'
                "?           # quotes have double quote delims for the speaker\'s name
                (.*?)        # GROUP: the name, url, or session id
                "?           # quotes have a closing double quote as well
                \]           # all start tags finish with a closed square bracket
                (.*?)        # GROUP: the content between the tags
                \[\/          # all end tags begin with an open square bracket and slash
                ((?P=tag))   # GROUP: the name of the found tag
                [:=]?        # there may be a single colon or equal sign
                ([a-zA-Z0-9]{0,10})?    # GROUP: either an attr or hex session id
                [:=]?        # there may be another singl colon or equal sign
                (.*?)        # GROUP: the hex session id
                \]           # all end tags finish with a closed square bracket
                ';

	/* Replace pairs of BBCode tags until none are found */
	$num_pairs = 0;
	$i = 1;
	$this->text = $text;
	if (VERBOSE)
	    echo "Starting tag pair search ..." . PHP_EOL;

	while ($tag_pair_count = preg_match_all('/' . $TAG_PAIR . '/xs', $text, $tag_pairs, PREG_SET_ORDER)) {
	    if (VERBOSE)
		echo "Number of pairs increasing from $num_pairs";
	    $num_pairs += $tag_pair_count;
	    if (VERBOSE)
		echo " to $num_pairs" . PHP_EOL;

	    $text = preg_replace_callback('/' . $TAG_PAIR . '/xss', array($this, '_handleUnknownTag'), $text);
	    $i++;
	}

	if (count($end_tags) != $num_pairs) {
	    printf("Number of found end tags (%d) does not match number of replaced tag pairs (%d)!" . PHP_EOL, count($end_tags), $num_pairs);
	}

	return $text;
    }

    /**
     * Determine the kind of tag in the match and call its handler
     *
     * $matches - a list of the groups in the matched string
     */
    function _handleUnknownTag($matches) {
	$name = strtolower($matches["tag"]);

	$method = '_do_' . $name;
	if (method_exists($this, $method)) {
	    return $this->{$method}($matches);
	} else {
	    printf("No method for tag: %s" . PHP_EOL, $name);
	}
	return "";
    }

    /**
     * Translate url tags from bbCode to xhtml: <a href=...>
     *
     * bbCode allows two kinds of url tags:
     *    [url]http://www.dcpa.org[/url]
     *    [url=http://mypage.org/]Click here[/url]
     *    These are matched as:
     *        ('url', '', '', 'http://www.dcpa.org', 'url', '', '')
     *        ('url', 'http', 'mypage.org/', 'Click here', 'url', '', '')
     */
    function _do_url($match) {
	if ($match[2]) {
	    $protocol = $match[2];
	    $address = $match[3];
	    $url = "$protocol://$address";
	} else {
	    $url = $match[4];
	}

	$anchor = $match[4];
	return "<a href='$url'>$anchor</a>";
    }

    /**
     * Translate bold tags from bbCode to xhtml: <strong>
     *
     * bbCode has a simple syntax for bold text:
     *     [b:5570ec4908]a line of bold text[/b:5570ec4908]
     *     This gets matched as:
     *         ('b', '5570ec4908', '', 'a line of bold text', 'b', '5570ec4908', '')
     */
    function _do_b($match) {
	return sprintf("<strong>%s</strong>", $match[4]);
    }

    /**
     * Translate italic tags from bbCode to xhtml: <em>
     *
     * bbCode has a simple syntax for italic text:
     *    [i:c570ec49a8]a line of italic text[/i:c570ec49a8]
     *    This gets matched as:
     *        ('i', 'c570ec49a8', '', 'a line of italic text', 'i', 'c570ec49a8', '')
     *
     */
    function _do_i($match) {
	return sprintf("<em>%s</em>", $match[4]);
    }

    /**
     * Translate underline tags from bbCode to xhtml
     *
     *    bbCode has a simple syntax for underlined text:
     *        [u:5570ec4908]underlined text[/u:5570ec4908]
     *        This gets matched as:
     *            ('u', '5570ec4908', '', 'underlined text', 'u', '5570ec4908', '')
     *
     */
    function _do_u($match) {
	return sprintf('<span style="text-decoration: underline">%s</span>', $match[4]);
    }

    /**
     * Translate striketrhough tags from bbCode to xhtml
     *
     *    bbCode has a simple syntax for strikethrough text:
     *        [s]strike-trough text[/s]
     *        This gets matched as:
     *            ('s', '', '', 'strike-through text', 's', '', '')
     *
     */
    function _do_s($match) {
	return sprintf('<span style="text-decoration:line-through">%s</span>', $match[4]);
    }

    /**
     * Translate size tags from bbCode to xhtml
     *
     * NOTE: sized text does not have an official xhtml standard tag.
     * A span is used:
     *    <span style="font-size: $SIZE">text</span>
     *
     *    bbCode has a slightly complex syntax for size:
     *        [size=18:]big text[/size:]
     *        This gets matched as:
     *            ('size', '18', '5570ec4908', 'big text', 'size', '5570ec4908', '')
     *
     */
    function _do_size($match) {
	return sprintf('<span style="font-size: %spx">%s</span>', $match[2], $match[4]);
    }

    /**
     * Translate color tags from bbCode to xhtml
     *
     * NOTE: colord text does not have an official xhtml standard tag.
     * A span is used instead:
     * <span style="color: $COLOR">text</span>
     *
     * bbCode has a slightly complex syntax for for color:
     *     [color=darkred:10831efb71]colored text[/color:10831efb71]
     *     This gets matched as:
     *         ('color', 'darkred', '10831efb71', 'colored text', 'color', '10831efb71', '')
     *
     */
    function _do_color($match) {
	if ($match[2])
	    $color = $match[2];
	else
	    $color = $match[3];
	return sprintf('<span style="color: %s">%s</span>', $color, $match[4]);
    }

    /**
     * Translate quote tags from bbCode to xhtml: <blockquote>
     *
     * bbCode allows two kinds of quoting:
     *    [quote:6f3b341246="Mark Twain]If you tell the truth you don't have to remember anything.[/quote:6f3b341246]
     *    [quote:ab1d8bb0ab]Anonymous quote[/quote:ab1d8bb0ab]
     *    These get matched as:
     *        ('quote', '6f3b341246', 'Mark Twain', 'If you tell the truth you don't have to remember anything.', 'quote', '6f3b341246', '')
     * 		 ('quote', 'ab1d8bb0ab', '', 'Anonymous quote', 'quote', 'ab1d8bb0ab', '')
     */
    function _do_quote($match) {
	if ($match[2])
	    $author = $match[2];
	else
	    $author = "Someone";

	$quote = $match[4];
	return sprintf("<blockquote><em>%s...</em>%s</blockquote>", $author, $quote);
    }

    /**
     * Translate attachement tags from bbCode to xhtml: <a>
     *
     * bbCode has a slightly complex syntax for attachements:
     *     [attachment:276a0f69c4]http://www.url.net/picture.jpg[/attachment:276a0f69c4]
     *    This gets matched as:
     *        ('attachment', '276a0f69c4', '', 'http://www.url.net/picture.jpg', 'attachment', '276a0f69c4', '')
     *
     * In order to convert attachements, this class needs access to phpBB database; set the connection with
     *  $code_converter = new BBCode(); $code_converter->setMigration($oMigration);
     * If zMigration instance is not provided, the [attachment] tag will be skipped.
     */
    function _do_attachment($matches) {
	if (!isset($this->oMigration))
	    return;

	// We need to have avaiable the file (path) and the filename
	// Filename is given as <!-- ?? -->filename<!-- ?? -->, so we need to skip the comment syntax
	$temp = explode('-->', $matches[4]);
	$temp = $temp[1];
	$temp = explode('<!', $temp);
	$file_name = $temp[0];

	// Retrieve info about current attachmnet from the database
	$query = "select * from " . $this->oMigration->db_info->db_table_prefix . "_attachments where real_filename = '" . $file_name . "'";
	$attach_result = $this->oMigration->query($query);
	$attach_info = $this->oMigration->fetch($attach_result); // We assume there is only one attachment with this name

	if (!$attach_info)
	    return;

	// Retrieve absolute path to file
	if (!isset($GLOBALS['UPLOAD_PATH'])) {
	    $query = "select config_value from " . $this->oMigration->db_info->db_table_prefix . "_config where config_name = 'upload_path'";
	    $path_result = $this->oMigration->query($query);
	    $path = $this->oMigration->fetch($path_result);
	    $GLOBALS['UPLOAD_PATH'] = $path->config_value;
	}

	$file_info = new stdClass;
	$file_info->filename = $attach_info->real_filename;
	$file_info->file = $this->oMigration->path . '\\' . $GLOBALS['UPLOAD_PATH'] . '\\' . $attach_info->physical_filename;
	$file_info->download_count = $attach_info->download_count;

	$this->attachments[] = $file_info;

	return;
	// Get file url
	// TODO: Retrieve absolute path from destination server instead of source server (as is now)
	// Until then, inline images will be skipped and only footer links will be added to posts
	// $url = $this->oMigration->getFileUrl($file_info->file);
	// If mimetype starts with image, print an image tag
	// if(strpos($attach_info->mimetype, "image") === 0){
	//     return sprintf("<img src='%s' alt='%s' />", $url, $file_info->filename);
	// }
	// else
	//     return sprintf("<a href='%s'>%s</a>", $url, $file_info->filename);
    }

    /**
     * Translate image tags from bbCode to xhtml: <img />
     *
     * bbCode has a slightly complex syntax for images:
     *     [img:276a0f69c4]http://www.url.net/picture.jpg[/img:276a0f69c4]
     *    This gets matched as:
     *        ('img', '276a0f69c4', '', 'http://www.url.net/picture.jpg', 'img', '276a0f69c4', '')
     */
    function _do_img($match) {
	$url = $match[4];
	$name = preg_replace('#^.*/#', '', $url);

	if ($match[2]) {
	    $dimensions = explode('x', $match[2]);
	}

	// TODO Test this further, I have no ideas what it does
	if ($this->fetchimages) {
	    $image_info = $this->ctx->fetch_image(urldecode($url), $name);
	    $url = $image_info['url'];
	    $name = $image_info['name'];
	}

	$img_src = "src=\"$url\" ";
	$img_alt = "alt=\"$name\" ";

	if ($match[3]) {
	    $img_attributes = $match[3];
	    return "<img" . $img_attributes . '" ' . $img_src . '/>';
	}

	if ($match[2])
	    $img_size = sprintf('width="%s" height="%s" ', $dimensions[0], $dimensions[1]);
	else
	    $img_size = "";

	return "<img " . $img_src . $img_size . $img_alt . '/>';
    }

    /**
     * Translate code tags from bbCode to xhtml: <code>
     *
     * bbCode has a slightly complicated syntax for code:
     *    [code:1:da277f1524]import os[/code:1:da277f1524]
     *    This gets matched as:
     *        ('code', '1', 'da277f1524', 'import os', 'code', '1', 'da277f1524')
     */
    function _do_code($match) {
	return sprintf("<code>%s</code>", $match[4]);
    }

    /**
     * Translate list tags from bbCode to xhtml: <ol> or <ul>
     *
     * Lists are written using list-style-type attribute.
     *
     * bbCode supports three kinds of lists:
     *     [list:ab1d8bb0ab]I have a list[/list:u:ab1d8bb0ab]
     *     [list=1:b0063dfd91]I have a numbered list[/list:o:b0063dfd91]
     *     [list=a:d406323d91]I have a lettered list[/list:o:d406323d91]
     *     This gets matched as:
     *         ('list', 'ab1d8bb0ab', '', "I have a list.", 'list', 'u', 'ab1d8bb0ab')
     *         ('list', '1', 'b0063dfd91', 'I have a numbered list', 'list', 'o', 'b0063dfd91')
     *         ('list', 'a', 'd406323d91', 'I have a lettered list', 'list', 'o', 'd406323d91')
     */
    function _do_list($match) {
	$type = $match[6];
	if (!$type)
	    $type = 'u';
	$text = $match[4];

	# Ordered lists are either numbered or lettered
	$style = '';
	if ($type == 'o')
	    if ($match[2] == 'a')
		$style = ' style="list-style-type: lower-alpha"';
	    else
		$style = ' style="list-style-type: arabic-numbers"';

	$start_tag = $type . 'l' . $style;
	$end_tag = $type . 'l';

	$bullet_re = "
                \[              # a bullet starts with an open square bracket
                \*              # followed by an asterisk,
                [:]?               # a colon,
                ([a-f0-9]{10})?    # and a 10-digit hex id
                \]              # finally, finish with a closed square bracket
                ";

	# Open the first list item
	# If the text starts with a bullet, replace it with <li>
	if (preg_match('/' . $bullet_re . '/xs', $text, $matches, PREG_OFFSET_CAPTURE)) {
	    $offset = $matches[0][1] + strlen($matches[0][0]);
	    $text = '<li>' . substr($text, $offset); // Keep only the text after the first [*] occurence
	}
	else
	    $text = '<li>' . $text;

	# Each subsequent bullet closes the previous one and starts its own
	$text = preg_replace('/' . $bullet_re . '/xs', '</li><li>', $text);

	# Close the final list item
	$text = $text . '</li>';

	return sprintf("<%s>%s</%s>", $start_tag, $text, $end_tag);
    }

    /**
     * Translate email tags from bbCode to xhtml <a href=mailto...>
     *
     * bbCode has a simple syntax for email:
     *     [email]mcp@tron.net[/email]
     *     This gets matched as:
     *         ('email', '', '', 'mcp@tron.net', 'email', '', '')
     *
     */
    function _do_email($match) {
	if ($match[3])
	    return sprintf('<a href="mailto:%s">%s</a>', $match[3], $match[4]);
	return sprintf('<a href="mailto:%s">%s</a>', $match[4], $match[4]);
    }

}

?>
