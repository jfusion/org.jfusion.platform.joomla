<?php namespace JFusion\Plugin\Platform;

/**
 * Abstract forum file
 *
 * PHP version 5
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */

use Captcha\Captcha;

use JFusion\Application\Application;
use JFusion\Css\Css;
use JFusion\Factory;
use JFusion\Framework;
use JFusion\Parser\Parser;
use JFusion\Plugin\Platform;
use JFusion\User\Userinfo;

use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Language\Text;
use Joomla\Uri\Uri;

use Joomla\Registry\Registry;

use Psr\Log\LogLevel;

use JFusionFunction;
use JCategories;
use JCategoryNode;
use JEventDispatcher;
use JFactory;

use Exception;
use \stdClass;

/**
 * Abstract interface for all JFusion forum implementations.
 *
 * @category  JFusion
 * @package   Models
 * @author    JFusion Team <webmaster@jfusion.org>
 * @copyright 2008 JFusion. All rights reserved.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link      http://www.jfusion.org
 */
class Joomla extends Platform
{
	const LAT = 0;
	const LCT = 1;
	const LCP = 2;

	var $helper;

	private $cookies = array();
	private $protected = array('format');
	private $curlLocation = null;

	/**
	 * @param string $instance instance name of this plugin
	 */
	function __construct($instance)
	{
		parent::__construct($instance);
		//get the helper object
		$this->helper = & Factory::getHelper($this->getJname(), $this->getName());
	}

    /**
     * Returns the URL to a thread of the integrated software
     *
     * @param int $threadid threadid
     *
     * @return string URL
     */
    function getThreadURL($threadid)
    {
        return '';
    }

    /**
     * Returns the URL to a post of the integrated software
     *
     * @param int $threadid threadid
     * @param int $postid   postid
     *
     * @return string URL
     */
    function getPostURL($threadid, $postid)
    {
        return '';
    }

    /**
     * Returns the URL to a userprofile of the integrated software
     *
     * @param int|string $userid userid
     *
     * @return string URL
     */
    function getProfileURL($userid)
    {
        return '';
    }

    /**
     * Retrieves the source path to the user's avatar
     *
     * @param int|string $userid software user id
     *
     * @return string with source path to users avatar
     */
    function getAvatar($userid)
    {
        return '';
    }

    /**
     * Returns the URL to the view all private messages URL of the integrated software
     *
     * @return string URL
     */
    function getPrivateMessageURL()
    {
        return '';
    }

    /**
     * Returns the URL to a view new private messages URL of the integrated software
     *
     * @return string URL
     */
    function getViewNewMessagesURL()
    {
        return '';
    }

    /**
     * Returns the URL to a get private messages URL of the integrated software
     *
     * @param int|string $puser_id userid
     *
     * @return array
     */
    function getPrivateMessageCounts($puser_id)
    {
        return array('unread' => 0, 'total' => 0);
    }

    /**
     * Returns the an array with SQL statements used by the activity module
     *
     * @param array  $usedforums    array with used forums
     * @param string $result_order  ordering of results
     *
     * @return array
     */
    function getActivityQuery($usedforums, $result_order)
    {
        return array();
    }

    /**
     * Returns the read status of a post based on the currently logged in user
     *
     * @param $post object with post data from the results returned from getActivityQuery
     * @return int
     */
    function checkReadStatus(&$post)
    {
        return 0;
    }

    /**
     * Returns the a list of forums of the integrated software
     *
     * @return array List of forums
     */
    function getForumList()
    {
        return array();
    }

    /**
     * Filter forums from a set of results sent in / useful if the plugin needs to restrict the forums visible to a user
     *
     * @param object &$results set of results from query
     * @param int    $limit    limit results parameter as set in the module's params; used for plugins that cannot limit using a query limiter
     */
    function filterActivityResults(&$results, $limit = 0)
    {
    }

    /************************************************
    * Functions For JFusion Discussion Bot Plugin
    ***********************************************/
    /**
     * Returns the URL to the reply page for a thread
     * @param integer $forumid
     * @param integer $threadid
     *
     * @return string URL
     */
    function getReplyURL($forumid, $threadid)
    {
        return '';
    }

	/**
	 * Checks to see if a thread already exists for the content item and calls the appropriate function
	 *
	 * @param Registry  &$dbparams    object with discussion bot parameters
	 * @param object     &$contentitem object containing content information
	 * @param object|int &$threadinfo  object with threadinfo from lookup table
	 *
	 * @return string
	 * @throws Exception
	 */
	function checkThreadExists(&$dbparams, &$contentitem, &$threadinfo)
	{
		$action = 'unknown';
		$threadid = (int) (is_object($threadinfo)) ? $threadinfo->threadid : $threadinfo;
		$forumid = $this->getDefaultForum($dbparams, $contentitem);
		$existingthread = (empty($threadid)) ? false : $this->getThread($threadid);

		if(!empty($forumid)) {
			if(!empty($existingthread)) {
				//datetime post was last updated
				if (isset($threadinfo->modified)) {
					$postModified = $threadinfo->modified;
				} else {
					$postModified = 0;
				}
				//datetime content was last updated
				$contentModified = Factory::getDate($contentitem->modified)->toUnix();

				$this->debugger->addDebug('Thread exists...comparing dates');
				$this->debugger->addDebug('Content Modification Date: ' . $contentModified . ' (' . date('Y-m-d H:i:s', $contentModified) . ')');
				$this->debugger->addDebug('Thread Modification Date: ' . $postModified . '  (' . date('Y-m-d H:i:s', $postModified) . ')');
				$this->debugger->addDebug('Is ' . $contentModified . ' > ' . $postModified . ' ?');
				if($contentModified > $postModified && $postModified != 0) {
					$this->debugger->addDebug('Yes...attempting to update thread');
					//update the post if the content has been updated
					try {
						$this->updateThread($dbparams, $existingthread, $contentitem);

						$action = 'updated';
					} catch (Exception $e) {
						throw $e;
					}
				} else {
					$this->debugger->addDebug('No...thread unchanged');
				}
			} else {
				$this->debugger->addDebug('Thread does not exist...attempting to create thread');
		    	//thread does not exist; create it
				try {
					$threadinfo = $this->createThread($dbparams, $contentitem, $forumid);
					$action = 'created';
				} catch (Exception $e) {
					throw $e;
				}
	        }
		} else {
			throw new \RuntimeException(Text::_('FORUM_NOT_CONFIGURED'));
		}
		return $action;
	}

    /**
     * Checks to see if a thread is locked
     *
     * @param 	int 	$threadid	thread id
     *
     * @return 	boolean 			true if locked
     */
    function getThreadLockedStatus($threadid) {
        //assume false
        return false;
    }

    /**
     * Retrieves the default forum based on section/category stipulations or default set in the plugins config
     *
     * @param Registry &$dbparams    discussion bot parameters
     * @param object &$contentitem object containing content information
     *
     * @return int Returns id number of the forum
     */
	function getDefaultForum(&$dbparams, &$contentitem)
	{
		//set some vars
		$forumid = $dbparams->get('default_forum');
		$catid = $contentitem->catid;
		$option = Factory::getApplication()->input->getCmd('option');

		if ($option == 'com_k2' || $option == 'com_content') {
    		//determine default forum

	        $param_name = ($option == 'com_k2') ? 'pair_k2_categories' : 'pair_categories';
    		$categories = $dbparams->get($param_name);
    		if(!empty($categories)) {
    			$pairs = base64_decode($categories);
    			$categoryPairs = @unserialize($pairs);
    			if ($categoryPairs === false) {
    			    $categoryPairs = array();
    			}
    		} else {
    			$categoryPairs = array();
    		}

    		if(array_key_exists($catid, $categoryPairs)) {
    			$forumid = $categoryPairs[$catid];
			} elseif (($option == 'com_k2' && isset($contentitem->category)) || ($option == 'com_content')) {
    		    //let's see if a parent has been assigned a forum
    		    if ($option == 'com_k2') {
    		        //see if a parent category is included
    		        $db = Factory::getDBO();
                    $stop = false;
                    $parent_id = $contentitem->category->parent;;
                    while (!$stop) {
                        if (!empty($parent_id)) {
                            if(array_key_exists($parent_id, $categoryPairs)) {
                                $stop = true;
                                $forumid = $categoryPairs[$parent_id];
                            } else {
                                //get the parent's parent
	                            $query = $db->getQuery(true)
		                            ->select('parent')
		                            ->from('#__k2_categories')
		                            ->where('id = ' . (int)$parent_id);

                                $db->setQuery($query);
                                //keep going up
                                $parent_id = $db->loadResult();
                            }
                        } else {
                            //at the top
                            $stop = true;
                        }
                    }
    		    } else {
    		        $JCat = JCategories::getInstance('Content');
                    /**
                     * @ignore
                     * @var $cat JCategoryNode
                     */
                    $cat = $JCat->get($catid);
            		if ($cat) {
	    		        $parent_id = $cat->getParent()->id;
	                    if ($parent_id !== 'root') {
	                        $stop = false;
	                        while (!$stop) {
	                            if (array_key_exists($parent_id, $categoryPairs)) {
	                                $forumid = $categoryPairs[$parent_id];
	                                $stop = true;
	                            } else {
	                                //keep going up so get the parent's parent id
                                    /**
                                     * @ignore
                                     * @var $parent JCategoryNode
                                     */
	                                $parent = $JCat->get($parent_id);
	                                $parent_id = $parent->getParent()->id;
	                                if ($parent_id == 'root') {
	                                    $stop = true;
	                                }
	                            }
	                        }
	                    }
            		}
    		    }
    		}
		}

		return $forumid;
	}

    /**
     * Retrieves thread information
     * $result->forumid
     * $result->threadid (yes add it even though it is passed in as it will be needed in other functions)
     * $result->postid - this is the id of the first post in the thread
     *
     * @param int $threadid Id of specific thread
     *
     * @return object Returns object with thread information
     */
    function getThread($threadid)
    {
        return null;
    }

    /**
     * Function that determines the author of an article or returns the default user if one is not found
     * For the discussion bot
     *
     * @param Registry &$dbparams    object with discussion bot parameters
     * @param object &$contentitem contentitem
     *
     * @return int forum's userid
     */
	function getThreadAuthor(&$dbparams, &$contentitem)
	{
		if($dbparams->get('use_article_userid', 1)) {
			//find this user in the forum

			$userlookup = new Userinfo('joomla_int');
			$userlookup->userid = $contentitem->created_by;

			$PluginUser = Factory::getUser($this->getJname());
			$userlookup = $PluginUser->lookupUser($userlookup);

			if(!$userlookup) {
				$id = $dbparams->get('default_userid');
			} else {
				$id = $userlookup->userid;
			}
		} else {
			$id = $dbparams->get('default_userid');
		}
		return $id;
	}

	/**
	 * Creates new thread and posts first post
	 *
	 * @param object &$params      discussion bot parameters
	 * @param object &$contentitem containing content information
	 * @param int    $forumid      forum to create thread
	 *
	 * @return \stdClass
	 */
    function createThread(&$params, &$contentitem, $forumid)
    {
	    return new stdClass();
    }

    /**
     * Updates information in a specific thread/post
     *
     * @param object &$params         discussion bot parameters
     * @param object &$existingthread existing thread info
     * @param object &$contentitem    content item
     */
    function updateThread(&$params, &$existingthread, &$contentitem)
    {
    }

    /**
     * Returns an object of columns used in createPostTable()
     * Saves from having to repeat the same code over and over for each plugin
     * For example:
     * $columns->userid = 'userid'
     * $columns->username = 'username';
     * $columns->name = 'realName'; //if applicable
     * $columns->dateline = 'dateline';
     * $columns->posttext = 'pagetext';
     * $columns->posttitle = 'title';
     * $columns->postid = 'postid';
     * $columns->threadid = 'threadid';
     * $columns->threadtitle = 'threadtitle'; //optional
     * $columns->guest = 'guest';
     *
     * @return object with column names
     */
    function getDiscussionColumns()
    {
        return null;
    }

	/**
	 * Prepares the body for the first post in a thread
	 *
	 * @param Registry &$dbparams 		object with discussion bot parameters
	 * @param object	$contentitem 	object containing content information
	 *
	 * @return string
	 */
	function prepareFirstPostBody(&$dbparams, $contentitem)
	{
		//set what should be posted as the first post
		$post_body = $dbparams->get('first_post_text', 'intro');

		$text = '';

		if($post_body == 'intro') {
			//prepare the text for posting
			$text .= $contentitem->introtext;
		} elseif($post_body == 'full') {
			//prepare the text for posting
			$text .= $contentitem->introtext . $contentitem->fulltext;
		}

		//create link
		$show_link = $dbparams->get('first_post_link', 1);
		//add a link to the article; force a link if text body is set to none so something is returned
		if($show_link || $post_body == 'none') {
			$link_text = $dbparams->get('first_post_link_text');
			if(empty($link_text)) {
				$link_text = Text::_('DEFAULT_ARTICLE_LINK_TEXT');
			} else {
				if($dbparams->get('first_post_link_type') == 'image') {
					$link_text = '<img src="' . $link_text . '">';
				}
			}

			$text .= (!empty($text)) ? '<br /><br />' : '';
			$text .= JFusionFunction::createJoomlaArticleURL($contentitem, $link_text);
		}

		//prepare the content
		$this->prepareText($text, 'forum', new Registry());

		return $text;
	}

	/**
	 * Prepares text for various areas
	 *
	 * @param string &$text             Text to be modified
	 * @param string $for              (optional) Determines how the text should be prepared.
	 *                                  Options for $for as passed in by JFusion's plugins and modules are:
	 *                                  joomla (to be displayed in an article; used by discussion bot)
	 *                                  forum (to be published in a thread or post; used by discussion bot)
	 *                                  activity (displayed in activity module; used by the activity module)
	 *                                  search (displayed as search results; used by search plugin)
	 * @param Registry $params          (optional) Joomla parameter object passed in by JFusion's module/plugin
	 *
	 * @return array  $status           Information passed back to calling script such as limit_applied
	 */
	function prepareText(&$text, $for = 'forum', $params = null)
	{
		$status = array();
		if ($for == 'forum') {
			//first thing is to remove all joomla plugins
			preg_match_all('/\{(.*)\}/U', $text, $matches);
			//find each thread by the id
			foreach ($matches[1] AS $plugin) {
				//replace plugin with nothing
				$text = str_replace('{' . $plugin . '}', "", $text);
			}
		} elseif ($for == 'joomla' || ($for == 'activity' && $params->get('parse_text') == 'html')) {
			$options = array();
			if (!empty($params) && $params->get('character_limit', false)) {
				$status['limit_applied'] = 1;
				$options['character_limit'] = $params->get('character_limit');
			}
			$parser = new Parser();
			$text = $parser->parseCode($text, 'html', $options);
		} elseif ($for == 'search') {
			$parser = new Parser();
			$text = $parser->parseCode($text, 'plaintext');
		} elseif ($for == 'activity') {
			if ($params->get('parse_text') == 'plaintext') {
				$options = array();
				$options['plaintext_line_breaks'] = 'space';
				if ($params->get('character_limit')) {
					$status['limit_applied'] = 1;
					$options['character_limit'] = $params->get('character_limit');
				}
				$parser = new Parser();
				$text = $parser->parseCode($text, 'plaintext', $options);
			}
		}
		return $status;
	}

	/**
	 * Retrieves the posts to be displayed in the content item if enabled
	 *
	 * @param Registry $dbparams
	 * @param object $existingthread object with forumid, threadid, and postid (first post in thread)
	 * @param int $start
	 * @param int $limit
	 * @param string $sort
	 *
	 * @internal param object $params object with discussion bot parameters
	 *
	 * @return array or object Returns retrieved posts
	 */
    function getPosts($dbparams, $existingthread, $start, $limit, $sort)
    {
        return array();
    }
    /**
     * Returns the total number of posts in a thread
     *
     * @param object &$existingthread object with forumid, threadid, and postid (first post in thread)
     *
     * @return int
     */
    function getReplyCount($existingthread)
    {
        return 0;
    }

    /**
     * Loads required quick reply includes into the main document so that ajax will work properly if initiating a discussion manually.  It is best
     * to load any files but return any standalone JS declarations.
     *
     * @return string $js JS declarations
     */

	function loadQuickReplyIncludes() {
		//using markitup http://markitup.jaysalvat.com/ for bbcode textbox
		$document = JFactory::getDocument();

		$path = 'plugins/content/jfusion/discussbot/markitup';

		$document->addScript(JFusionFunction::getJoomlaURL() . $path . '/jquery.markitup.js');
		$document->addScript(JFusionFunction::getJoomlaURL() . $path . '/sets/bbcode/set.js');
		$document->addStylesheet(JFusionFunction::getJoomlaURL() . $path . '/skins/simple/style.css');
		$document->addStylesheet(JFusionFunction::getJoomlaURL() . $path . '/sets/bbcode/style.css');

		$js = <<<JS
			JFusion.loadMarkitup = true;
			jQuery.noConflict();
JS;
		return $js;
	}

    /**
     * Returns HTML of a quick reply
     *
     * @param Registry &$dbparams       object with discussion bot parameters
     * @param boolean $showGuestInputs toggles whether to show guest inputs or not
     *
     * @return string of html
     */
	function createQuickReply(&$dbparams, $showGuestInputs)
	{
		$html = '';
		if($showGuestInputs) {
			$username = Factory::getApplication()->input->post->get('guest_username', '');
            $jusername = Text::_('USERNAME');
            $html = <<<HTML
            <table>
                <tr>
                    <td>
                        {$jusername}:
                    </td>
                    <td>
                        <input name='guest_username' value='{$username}' class='inputbox'/>
                    </td>
                </tr>
                {$this->createCaptcha($dbparams)}
            </table>
            <br />
HTML;

		}
		$quickReply = Factory::getApplication()->input->post->get('quickReply', '');
	   	$html .= '<textarea name="quickReply" class="inputbox quickReply" rows="15" cols="100">' . $quickReply . '</textarea><br />';
	   	return $html;
	}

    /**
     * Creates the html for the selected captcha for the discussion bot
     *
     * @param Registry $dbparams object with discussion bot parameters
     *
     * @return string
     */
	function createCaptcha($dbparams)
	{
		$html = '';
		$captcha_mode = $dbparams->get('captcha_mode', 'disabled');

		switch($captcha_mode) {
			case 'question':
				//answer/question method
				$question = $dbparams->get('captcha_question');
				if(!empty($question)) {
					$html .= '<tr><td>' . $question . ':</td><td><input name="captcha_answer" value="" class="inputbox"/></td></tr>';
				}
				break;
			case 'joomla15captcha':
				//using joomla15captcha (http://code.google.com/p/joomla15captcha)
				$dispatcher = JEventDispatcher::getInstance();
				$results = $dispatcher->trigger('onCaptchaRequired', array('jfusion.discussion'));
				if ($results[0])
					ob_start();
					$dispatcher->trigger('onCaptchaView', array('jfusion.discussion', 0, '<tr><td colspan=2><br />', '<br /></td></tr>'));
					$html .= ob_get_contents();
					ob_end_clean();
				break;
			case 'recaptcha':
				$html .= '<tr><td colspan="2">';
				try {
					$captcha = new Captcha();

					$captcha->setPublicKey($dbparams->get('recaptcha_publickey'));
					$captcha->setPrivateKey($dbparams->get('recaptcha_privatekey'));

					$captcha->setTheme($dbparams->get('recaptcha_theme', 'red'));

					$html .= $captcha->html();
				} catch (Exception $e) {
					$html .= $e->getMessage();
				}
				$html .= '</td></tr>';
				break;
			case 'custom':
				$html .= $this->createCustomCaptcha($dbparams);
				break;
			default:
				break;
		}

		return $html;
	}

    /**
     * Creates custom captcha html for this plugin
     *
     * @param object &$dbparams object with discussion bot parameters
     *
     * @return string with html
     */
	function createCustomCaptcha(&$dbparams)
	{
		Framework::raise(LogLevel::ERROR, Text::_('DISCUSSBOT_ERROR') . ': ' . Text::_('CUSTOM_CAPTCHA_NOT_IMPLEMENTED'), $this->getJname());
		return '';
	}

    /**
     * Verifies captcha of a guest post submitted by the discussion bot
     *
     * @param Registry &$dbparams object with discussion bot parameters
     *
     * @return boolean
     */
	function verifyCaptcha(&$dbparams)
	{
		//let's check for captcha
		$captcha_mode = $dbparams->get('captcha_mode', 'disabled');
		$captcha_verification = false;

		switch($captcha_mode) {
			case 'question':
				//question/answer method
				$captcha_answer = Factory::getApplication()->input->post->get('captcha_answer', '');
				if(!empty($captcha_answer) && $captcha_answer == $dbparams->get('captcha_answer')) {
					$captcha_verification = true;
				}
				break;
			case "joomla15captcha":
				//using joomla15captcha (http://code.google.com/p/joomla15captcha)
				$dispatcher = JEventDispatcher::getInstance();
				$results = $dispatcher->trigger('onCaptchaRequired', array('jfusion.discussion'));
				if ($results[0]) {
					$captchaparams = array(Factory::getApplication()->input->post->get('captchacode', '')
						, Factory::getApplication()->input->post->get('captchasuffix', '')
						, Factory::getApplication()->input->post->get('captchasessionid', ''));
					$results = $dispatcher->trigger('onCaptchaVerify', $captchaparams);
					if ($results[0]) {
						$captcha_verification = true;
					}
				}
				break;
			case 'recaptcha':
				//using reCAPTCHA (http://recaptcha.net)
				try {
					$captcha = new Captcha();

					$captcha->setPublicKey($dbparams->get('recaptcha_publickey'));
					$captcha->setPrivateKey($dbparams->get('recaptcha_privatekey'));

					$response_field  = Factory::getApplication()->input->post->getString('recaptcha_response_field', '');
					$challenge_field = Factory::getApplication()->input->post->getString('recaptcha_challenge_field', '');

					$responce = $captcha->check($challenge_field, $response_field);

					if ($responce->isValid()) {
						$captcha_verification = true;
					}
				} catch (Exception $e) {
					Framework::raise(LogLevel::ERROR, $e, $this->getJname());
				}
				break;
			case 'disabled':
				$captcha_verification = true;
				break;
			default:
				$captcha_verification = $this->verifyCustomCaptcha($dbparams);
				break;
		}

		return $captcha_verification;
	}

    /**
     * Verifies custom captcha of a JFusion plugin
     *
     * @param object &$dbparams object with discussion bot parameters
     *
     * @return boolean
     */
	function verifyCustomCaptcha(&$dbparams)
	{
		Framework::raise(LogLevel::ERROR, Text::_('DISCUSSBOT_ERROR') . ': ' . Text::_('CUSTOM_CAPTCHA_NOT_IMPLEMENTED'), $this->getJname());
		return false;
	}

	/**
	 * Creates a post from the quick reply
	 *
	 * @param Registry $params      object with discussion bot parameters
	 * @param stdClass  $ids         stdClass with forum id ($ids->forumid, thread id ($ids->threadid) and first post id ($ids->postid)
	 * @param object    $contentitem object of content item
	 * @param Userinfo  $userinfo    object info of the forum user
	 * @param stdClass  $postinfo    object with post info
	 *
	 * @throws \RuntimeException
	 *
	 * @return stdClass
	 */
	function createPost($params, $ids, $contentitem, Userinfo $userinfo, $postinfo)
	{
		$post = new stdClass();
		$post->postid = 0;
		$post->moderated = 0;

		throw new \RuntimeException(Text::_('METHOD_NOT_IMPLEMENTED'));
	}

    /**
     * @param array $forumids
     *
     * @return array
     */
    function filterForumList($forumids)
    {
        return $forumids;
    }

    /**
     * @param array $config
     * @param $view
     * @param Registry $params
     *
     * @return string
     */
    function renderActivityModule($config, $view, $params)
    {
        return Text::_('METHOD_NOT_IMPLEMENTED');
    }

	/**
	 * Function that that is used to keep sessions in sync and/or alive
	 *
	 * @param boolean $keepalive    Tells the function to regenerate the inactive session as long as the other is active
	 * unless there is a persistent cookie available for inactive session
	 *
	 * @return integer 0 if no session changes were made, 1 if session created
	 */
	function syncSessions($keepalive = false)
	{
		return 0;
	}

	/**
	 * @param array $config
	 * @param $view
	 * @param Registry $params
	 *
	 * @return string
	 */
	function renderUserActivityModule($config, $view, $params)
	{
		return Text::_('METHOD_NOT_IMPLEMENTED');
	}

	/************************************************
	 * Functions For JFusion Who's Online Module
	 ***********************************************/

	/**
	 * Returns a query to find online users
	 * Make sure columns are named as userid, username, username_clean (if applicable), name (of user), and email
	 *
	 * @param array $usergroups
	 *
	 * @return string online user query
	 */
	function getOnlineUserQuery($usergroups = array())
	{
		return '';
	}

	/**
	 * Returns number of guests
	 *
	 * @return int
	 */
	function getNumberOnlineGuests()
	{
		return 0;
	}

	/**
	 * Returns number of logged in users
	 *
	 * @return int
	 */
	function getNumberOnlineMembers()
	{
		return 0;
	}

	/**
	 * @param array $config
	 * @param $view
	 * @param Registry $params
	 *
	 * @return string
	 */
	function renderWhosOnlineModule($config, $view, $params)
	{
		return Text::_('METHOD_NOT_IMPLEMENTED');
	}

	/**
	 * Set the language from Joomla to the integrated software
	 *
	 * @param Userinfo $userinfo - it can be null if the user is not logged for example.
	 *
	 * @throws \RuntimeException
	 *
	 * @return array nothing
	 */
	function setLanguageFrontEnd(Userinfo $userinfo = null)
	{
		throw new \RuntimeException(Text::_('METHOD_NOT_IMPLEMENTED'));
	}

	/************************************************
	 * Functions For JFusion Search Plugin
	 ***********************************************/

	/**
	 * Retrieves the search results to be displayed.  Placed here so that plugins that do not use the database can retrieve and return results
	 * Each result should include:
	 * $result->title = title of the post/article
	 * $result->section = (optional) section of  the post/article (shows underneath the title; example is Forum Name / Thread Name)
	 * $result->text = text body of the post/article
	 * $result->href = link to the content (without this, joomla will not display a title)
	 * $result->browsernav = 1 opens link in a new window, 2 opens in the same window
	 * $result->created = (optional) date when the content was created
	 *
	 * @param string &$text        string text to be searched
	 * @param string &$phrase      string how the search should be performed exact, all, or any
	 * @param Registry &$pluginParam custom plugin parameters in search.xml
	 * @param int    $itemid       what menu item to use when creating the URL
	 * @param string $ordering     ordering sent by Joomla: null, oldest, popular, category, alpha, or newest
	 *
	 * @return array of results as objects
	 */
	function getSearchResults(&$text, &$phrase, &$pluginParam, $itemid, $ordering)
	{
		//initialize plugin database
		$db = Factory::getDatabase($this->getJname());
		//get the query used to search
		$query = $this->getSearchQuery($pluginParam);
		//assign specific table columns to title and text
		$columns = $this->getSearchQueryColumns();
		//build the query
		if ($phrase == 'exact') {
			$where = '((LOWER(' . $columns->title . ') LIKE \'%' . $text . '%\') OR (LOWER(' . $columns->text . ') like \'%' . $text . '%\'))';
		} else {
			$words = explode(' ', $text);
			$wheres = array();
			foreach ($words as $word) {
				$wheres[] = '((LOWER(' . $columns->title . ') LIKE \'%' . $word . '%\') OR (LOWER(' . $columns->text . ') like \'%' . $word . '%\'))';
			}
			if ($phrase == 'all') {
				$separator = 'AND';
			} else {
				$separator = 'OR';
			}
			$where = '(' . implode(') ' . $separator . ' (', $wheres) . ')';
		}
		//pass the where clause into the plugin in case it wants to add something
		$this->getSearchCriteria($where, $pluginParam, $ordering);
		$query.= ' WHERE ' . $where;
		//add a limiter if set
		$limit = $pluginParam->get('search_limit', '');
		if (!empty($limit)) {
			$db->setQuery($query, 0, $limit);
		} else {
			$db->setQuery($query);
		}
		$results = $db->loadObjectList();
		//pass results back to the plugin in case they need to be filtered
		$this->filterSearchResults($results, $pluginParam);
		//load the results
		if (is_array($results)) {
			foreach ($results as $result) {
				//add a link
				$href = JFusionFunction::routeURL($this->getSearchResultLink($result), $itemid, $this->getJname(), false);
				$result->href = $href;
				//open link in same window
				$result->browsernav = 2;
				//clean up the text such as removing bbcode, etc
				$this->prepareText($result->text, 'search', $pluginParam);
				$this->prepareText($result->title, 'search', $pluginParam);
				$this->prepareText($result->section, 'search', $pluginParam);
			}
		}
		return $results;
	}

	/**
	 * Assigns specific db columns to title and text of content retrieved
	 *
	 * @return object Db columns assigned to title and text of content retrieved
	 */
	function getSearchQueryColumns()
	{
		$columns = new stdClass();
		$columns->title = '';
		$columns->text = '';
		return $columns;
	}

	/**
	 * Generates SQL query for the search plugin that does not include where, limit, or order by
	 *
	 * @param Registry &$pluginParam custom plugin parameters in search.xml
	 *
	 * @return string Returns query string
	 */
	function getSearchQuery(&$pluginParam)
	{
		return '';
	}

	/**
	 * Add on a plugin specific clause;
	 *
	 * @param string &$where reference to where clause already generated by search bot; add on plugin specific criteria
	 * @param Registry &$pluginParam custom plugin parameters in search.xml
	 * @param string $ordering     ordering sent by Joomla: null, oldest, popular, category, alpha, or newest
	 */
	function getSearchCriteria(&$where, &$pluginParam, $ordering)
	{
	}

	/**
	 * Filter out results from the search ie forums that a user does not have permission to
	 *
	 * @param array &$results object list of search query results
	 * @param Registry &$pluginParam custom plugin parameters in search.xml
	 */
	function filterSearchResults(&$results, &$pluginParam)
	{
	}

	/**
	 * Returns the URL for a post
	 *
	 * @param mixed $vars mixed
	 *
	 * @return string with URL
	 */
	function getSearchResultLink($vars)
	{
		return '';
	}

	/**
	 * Function to check if a given itemid is configured for the plugin in question.
	 *
	 * @param int $itemid
	 *
	 * @return bool
	 */
	public final function isValidItemID($itemid)
	{
		$result = false;
		if ($itemid) {
			$app = JFactory::getApplication();
			$menus = $app->getMenu('site');
			/**
			 * @var Registry $params
			 */
			$params = $menus->getParams($itemid);
			if ($params) {
				$jPluginParam = unserialize(base64_decode($params->get('JFusionPluginParam')));
				if (is_array($jPluginParam) && $jPluginParam['jfusionplugin'] == $this->getJname()) {
					$result = true;
				}
			}
		}
		return $result;
	}

	/**
	 * gets the visual html output from the plugin
	 *
	 * @param object &$data object containing all frameless data
	 *
	 * @return void
	 */
	function getBuffer(&$data)
	{
		trigger_error('&$data deprecreated use $this->data instead', E_USER_DEPRECATED);
		try {
			$this->curlFrameless($data);

			if (isset($data->location)) {
				$location = str_replace($data->integratedURL, '', $data->location);
				$location = $this->fixUrl(array(1 => $location));
				JFactory::getApplication()->redirect($location);
			}
		} catch (\Exception $e) {
			Framework::raise(LogLevel::WARNING, $e, $this->getJname());
		}
	}

	/**
	 * function that parses the HTML body and fixes up URLs and form actions
	 * @param &$data
	 */
	function parseBody(&$data)
	{
		$regex_body = array();
		$replace_body = array();
		$callback_body = array();

		$siteuri = new Uri($data->integratedURL);
		$path = $siteuri->getPath();

		//parse anchors
		if(!empty($data->parse_anchors)) {
			$regex_body[]	= '#href=(?<quote>["\'])\#(.*?)(\k<quote>)#mS';
			$replace_body[]	= 'href=$1' . $data->fullURL . '#$2$3';
			$callback_body[] = '';
		}

		//parse relative URLS
		if(!empty($data->parse_rel_url)) {
			$regex_body[]	= '#(?<=href=["\'])\./(.*?)(?=["\'])#mS';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';

			$regex_body[]	= '#(?<=href=["\'])(?!\w{0,10}://|\w{0,10}:|\/)(.*?)(?=["\'])#mS';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';
		}

		if(!empty($data->parse_abs_path)) {
			$regex_body[]	= '#(?<=action=["\']|href=["\'])' . $path . '(.*?)(?=["\'])#mS';
			$replace_body[]	= '';
			$callback_body[] = 'fixUrl';

			$regex_body[] = '#(?<=href=["\'])' . $path . '(.*?)(?=["\'])#m';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';

			$regex_body[] = '#(src=["\']|background=["\']|url\()' . $path . '(.*?)(["\']|\))#mS';
			$replace_body[]	= '$1' . $data->integratedURL . '$2$3';
			$callback_body[] = '';
		}

		//parse absolute URLS
		if(!empty($data->parse_abs_url)) {
			$regex_body[]	= '#(?<=href=["\'])' . $data->integratedURL . '(.*?)(?=["\'])#m';
			$replace_body[] = '';
			$callback_body[] = 'fixUrl';
		}

		//convert relative links from images into absolute links
		if(!empty($data->parse_rel_img)) {
// (?<quote>["\'])
// \k<quote>
			$regex_body[] = '#(src=["\']|background=["\']|url\()\./(.*?)(["\']|\))#mS';
			$replace_body[]	= '$1' . $data->integratedURL . '$2$3';
			$callback_body[] = '';

			$regex_body[] = '#(src=["\']|background=["\']|url\()(?!\w{0,10}://|\w{0,10}:|\/)(.*?)(["\']|\))#mS';
			$replace_body[]	= '$1' . $data->integratedURL . '$2$3';
			$callback_body[] = '';
		}

		//parse form actions
		if(!empty($data->parse_action)) {
			if (!empty($data->parse_abs_path)) {
				$regex_body[] = '#action=[\'"]' . $path . '(.*?)[\'"](.*?)>#m';
				$replace_body[]	= '';
				$callback_body[] = 'fixAction';
			}
			if (!empty($data->parse_abs_url)) {
				$regex_body[] = '#action=[\'"]' . $data->integratedURL . '(.*?)[\'"](.*?)>#m';
				$replace_body[]	= '';
				$callback_body[] = 'fixAction';
			}
			if (!empty($data->parse_rel_url)) {
				$regex_body[] = '#action=[\'"](?!\w{0,10}://|\w{0,10}:|\/)(.*?)[\'"](.*?)>#m';
				$replace_body[]	= '';
				$callback_body[] = 'fixAction';
			}
		}

		//parse relative popup links to full url links
		if(!empty($data->parse_popup)) {
			$regex_body[] = '#window\.open\(\'(?!\w{0,10}://)(.*?)\'\)#mS';
			$replace_body[]	= 'window.open(\'' . $data->integratedURL . '$1\'';
			$callback_body[] = '';
		}

		$value = $data->bodymap;
		$value = @unserialize($value);
		if(is_array($value)) {
			foreach ($value['value'] as $key => $val) {
				$regex = html_entity_decode($value['value'][$key]);
//			    $regex = rtrim($regex, ';');
//			    $regex = eval("return '$regex';");

				$replace = html_entity_decode($value['name'][$key]);
//			    $replace = rtrim($replace, ';');
//			    $replace = eval("return '$replace';");

				if ($regex && $replace) {
					$regex_body[]	= $regex;
					$replace_body[]	= $replace;
					$callback_body[] = '';
				}
			}
		}

		foreach ($regex_body as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_body[$k])) {
				$data->body = preg_replace_callback($regex_body[$k], array(&$this, $callback_body[$k]), $data->body);
			} else {
				$data->body = preg_replace($regex_body[$k], $replace_body[$k], $data->body);
			}
		}

		$this->_parseBody($data);
	}

	/**
	 * function that parses the HTML body and fixes up URLs and form actions
	 * @param &$data
	 */
	function _parseBody(&$data)
	{
	}

	/**
	 * function that parses the HTML header and fixes up URLs
	 * @param &$data
	 */
	function parseHeader(&$data)
	{
		// Define our preg arrays
		$regex_header = array();
		$replace_header	= array();
		$callback_header = array();

		//convert relative links into absolute links
		$siteuri = new Uri($data->integratedURL);
		$path = $siteuri->getPath();

		$regex_header[]	= '#(href|src)=(?<quote>["\'])' . $path . '(.*?)(\k<quote>)#Si';
		$replace_header[] = '$1=$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		$regex_header[]		= '#(href|src)=(?<quote>["\'])(\.\/|/)(.*?)(\k<quote>)#iS';
		$replace_header[]	= '$1=$2' . $data->integratedURL . '$4$5';
		$callback_header[] = '';

		$regex_header[] 	= '#(href|src)=(?<quote>["\'])(?!\w{0,10}://)(.*?)(\k<quote>)#mSi';
		$replace_header[]	= '$1=$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		$regex_header[]		= '#@import(.*?)(?<quote>["\'])' . $path . '(.*?)(\k<quote>)#Sis';
		$replace_header[]	= '@import$1$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		$regex_header[]		= '#@import(.*?)(?<quote>["\'])\.\/(.*?)(\k<quote>)#Sis';
		$replace_header[]	= '@import$1$2' . $data->integratedURL . '$3$4';
		$callback_header[] = '';

		//fix for URL redirects
		$parse_redirect = $this->params->get('parse_redirect');
		if(!empty($parse_redirect)) {
			$regex_header[] = '#(?<=<meta http-equiv="refresh" content=")(.*?)(?=")#mis';
			$replace_header[] = '';
			$callback_header[] = 'fixRedirect';
		}

		$value = $data->headermap;
		$value = @unserialize($value);
		if(is_array($value)) {
			foreach ($value['value'] as $key => $val) {
				$regex = html_entity_decode($value['value'][$key]);
//                $regex = rtrim($regex,';');
//                $regex = eval("return '$regex';");

				$replace = html_entity_decode($value['name'][$key]);
//                $replace = rtrim($replace,';');
//                $replace = eval("return '$replace';");

				if ($regex && $replace) {
					$regex_header[]		= $regex;
					$replace_header[]	= $replace;
					$callback_header[] = '';
				}
			}
		}
		foreach ($regex_header as $k => $v) {
			//check if we need to use callback
			if(!empty($callback_header[$k])) {
				$data->header = preg_replace_callback($regex_header[$k], array(&$this, $callback_header[$k]), $data->header);
			} else {
				$data->header = preg_replace($regex_header[$k], $replace_header[$k], $data->header);
			}
		}

		$this->_parseHeader($data);
	}

	/**
	 * function that parses the HTML header and fixes up URLs
	 * @param &$data
	 */
	function _parseHeader(&$data)
	{

	}

	/**
	 * Parsers the buffer received from getBuffer into header and body
	 * @param &$data
	 */
	function parseBuffer(&$data) {
		$pattern = '#<head[^>]*>(.*?)<\/head>.*?<body([^>]*)>(.*)<\/body>#si';
		$temp = array();

		preg_match($pattern, $data->buffer, $temp);
		if(!empty($temp[1])) $data->header = $temp[1];
		if(!empty($temp[3])) $data->body = $temp[3];

		$pattern = '#onload=["]([^"]*)#si';
		if(!empty($temp[2])) {
			$data->bodyAttributes = $temp[2];
			if(preg_match($pattern, $temp[2], $temp)) {
				$js ='<script language="JavaScript" type="text/javascript">';
				$js .= <<<JS
                if(window.addEventListener) { // Standard
                    window.addEventListener(\'load\', function(){
                        {$temp[1]}
                    }, false);
                } else if(window.attachEvent) { // IE
                    window.attachEvent(\'onload\', function(){
                        {$temp[1]}
                    });
                }
JS;
				$js .= '</script>';
				$data->header .= $js;
			}
		}
		unset($temp);
	}

	/**
	 * function that parses the HTML and fix the css
	 *
	 * @param object &$data data to parse
	 * @param string &$html data to parse
	 * @param bool $infile_only parse only infile (body)
	 */
	function parseCSS(&$data, &$html, $infile_only = false)
	{
		$jname = $this->getJname();

		if (empty($jname)) {
			$jname = Factory::getApplication()->input->get('Itemid');
		}

		$sourcepath = $data->css->sourcepath . $jname . '/';
		$urlpath = $data->css->url . $jname . '/';

		Folder::create($sourcepath . 'infile');
		if (!$infile_only) {
			//Outputs: apearpearle pear
			if ($data->parse_css) {
				if (preg_match_all('#<link(.*?type=[\'|"]text\/css[\'|"][^>]*)>#Si', $html, $css)) {
					foreach ($css[1] as $values) {
						if(preg_match('#href=[\'|"](.*?)[\'|"]#Si', $values, $cssUrl)) {
							$cssUrlRaw = $cssUrl[1];

							if (strpos($cssUrlRaw, '/') === 0) {
								$uri = new Uri($data->integratedURL);

								$cssUrlRaw = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port')) . $cssUrlRaw;
							}
							$filename = $this->cssCacheName(urldecode(htmlspecialchars_decode($cssUrl[1])));
							$filenamesource = $sourcepath . $filename;

							if (!is_file(Path::clean($filenamesource))) {
								$cssparser = new Css('#jfusionframeless');
								$result = $cssparser->ParseUrl($cssUrlRaw);
								if ($result !== false) {
									$content = $cssparser->GetCSS();
									File::write($filenamesource, $content);
								}
							}

							if (is_file(Path::clean($filenamesource))) {
								$html = str_replace($cssUrlRaw, $urlpath . $filename, $html);
							}
						}
					}
				}
			}
		}
		if ($data->parse_infile_css) {
			if (preg_match_all('#<style.*?type=[\'|"]text/css[\'|"].*?>(.*?)</style>#Sims', $html, $css)) {
				foreach ($css[1] as $key => $values) {
					$filename = md5($values) . '.css';
					$filenamesource = $sourcepath . 'infile/' . $filename;

					if (preg_match('#media=[\'|"](.*?)[\'|"]#Si', $css[0][$key], $cssMedia)) {
						$cssMedia = $cssMedia[1];
					} else {
						$cssMedia = '';
					}

					if (!is_file(Path::clean($filenamesource))) {
						$cssparser = new Css('#jfusionframeless');
						$cssparser->setUrl($data->integratedURL);
						$cssparser->ParseStr($values);
						$content = $cssparser->GetCSS();
						File::write($filenamesource, $content);
					}
					if (is_file(Path::clean($filenamesource))) {
						$data->css->files[] = $urlpath . 'infile/' . $filename;
						$data->css->media[] = $cssMedia;
					}
				}
				$html = preg_replace('#<style.*?type=[\'|"]text/css[\'|"].*?>(.*?)</style>#Sims', '', $html);
			}
		}
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	function cssCacheName($url) {
		$uri = new Uri($url);
		$filename = $uri->toString(array('path', 'query'));
		$filename = trim($filename, '/');

		$filename = str_replace(array('.css', '\\', '/', '|', '*', ':', ';', '?', '"', '<', '>', '=', '&'),
			array('', '', '-', '', '', '', '', '', '', '', '', ',', '_'),
			$filename);
		$filename .= '.css';
		return $filename;
	}

	/**
	 * Fix Url
	 *
	 * @param array $matches
	 *
	 * @return string url
	 */
	function fixUrl($matches)
	{
		$q = $matches[1];

		if (substr($this->data->baseURL, -1) != '/') {
			//non sef URls
			$q = str_replace('?', '&amp;', $q);
			$url = $this->data->baseURL . '&amp;jfile=' . $q;
		} elseif ($this->data->sefmode == 1) {
			$url = JFusionFunction::routeURL($q, Factory::getApplication()->input->getInt('Itemid'));
		} else {
			//we can just append both variables
			$url = $this->data->baseURL . $q;
		}
		return $url;
	}

	/**
	 * @param $matches
	 *
	 * @return string
	 */
	function fixAction($matches) {
		$url = $matches[1];
		$extra = $matches[2];
		$baseURL = $this->data->baseURL;

		$url = htmlspecialchars_decode($url);
		$Itemid = Factory::getApplication()->input->getInt('Itemid');
		//strip any leading dots
		if (substr($url, 0, 2) == './') {
			$url = substr($url, 2);
		}
		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$url_details = parse_url($url);
			$url_variables = array();
			if (!empty($url_details['query'])) {
				parse_str($url_details['query'], $url_variables);
			}
			$jfile = basename($url_details['path']);
			//set the correct action and close the form tag
			$replacement = 'action="' . $baseURL . '"' . $extra . '>';
			$replacement.= '<input type="hidden" name="jfile" value="' . $jfile . '"/>';
			$replacement.= '<input type="hidden" name="Itemid" value="' . $Itemid . '"/>';
			$replacement.= '<input type="hidden" name="option" value="com_jfusion"/>';
		} else {
			if ($this->data->sefmode == 1) {
				//extensive SEF parsing was selected
				$url = JFusionFunction::routeURL($url, $Itemid);
				$replacement = 'action="' . $url . '"' . $extra . '>';
				return $replacement;
			} else {
				//simple SEF mode
				$url_details = parse_url($url);
				$url_variables = array();
				if(!empty($url_details['query'])) {
					parse_str($url_details['query'], $url_variables);
				}
				$jfile = basename($url_details['path']);
				$replacement = 'action="' . $baseURL . $jfile . '"' . $extra . '>';
			}
		}
		unset($url_variables['option'], $url_variables['jfile'], $url_variables['Itemid']);

		//add any other variables
		if (is_array($url_variables)) {
			foreach ($url_variables as $key => $value) {
				$replacement.= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
			}
		}
		return $replacement;
	}

	/**
	 * @param array $matches
	 *
	 * @return string
	 */
	function fixRedirect($matches) {
		$baseURL = $this->data->baseURL;

		preg_match('#(.*?;url=)(.*)#mi', $matches[1], $matches2);
		list(, $timeout , $url) = $matches2;

		$uri = new Uri($url);
		$jfile = basename($uri->getPath());
		$query = $uri->getQuery(false);
		$fragment = $uri->getFragment();
		if (substr($baseURL, -1) != '/') {
			//non-SEF mode
			$url = $baseURL . '&amp;jfile=' . $jfile;
			if (!empty($query)) {
				$url.= '&amp;' . $query;
			}
		} else {
			//check to see what SEF mode is selected
			$sefmode = $this->params->get('sefmode');
			if ($sefmode == 1) {
				//extensive SEF parsing was selected
				$url = $jfile;
				if (!empty($query)) {
					$url.= '?' . $query;
				}
				$url = JFusionFunction::routeURL($url, Factory::getApplication()->input->getInt('Itemid'));
			} else {
				//simple SEF mode, we can just combine both variables
				$url = $baseURL . $jfile;
				if (!empty($query)) {
					$url.= '?' . $query;
				}
			}
		}
		if (!empty($fragment)) {
			$url .= '#' . $fragment;
		}
		//Framework::raise(LogLevel::WARNING, htmlentities($return), $this->getJname());
		return $timeout . $url;
	}

	/**
	 * function to generate url for wrapper
	 * @param &$data
	 *
	 * @return string returns the url
	 */
	function getWrapperURL($data)
	{
		//get the url
		$query = ($_GET);

		$jfile = Factory::getApplication()->input->get('jfile', 'index.php', 'raw');

		unset($query['option'], $query['jfile'], $query['Itemid'], $query['jFusion_Route'], $query['view'], $query['layout'], $query['controller'], $query['lang'], $query['task']);

		$queries = array();

		foreach($query as $key => $var) {
			$queries[] = $key . '=' . $var;
		}

		$wrap = $jfile . '?' . implode($queries, '&');

		$source_url = $this->params->get('source_url');

		return $source_url . $wrap;
	}

	/**
	 * @param $data
	 *
	 * @throws \RuntimeException
	 */
	private function curlFrameless(&$data) {
		$url = $data->source_url;

		$config = Factory::getConfig();
		$sefenabled = $config->get('sef');
		if(!empty($sefenabled)) {
			$current = new Uri($data->fullURL);
			$current = $current->toString();

			$index = new Uri($data->baseURL);
			$index = $index->toString(array('path', 'query'));

			$pos = strpos($current, $index);
			if ($pos !== false) {
				$current = substr($current, $pos + strlen($index));
			}
		} else {
			$current = Factory::getApplication()->input->get('jfile') . '?';
			$current .= $this->curlFramelessBuildUrl('GET');
		}
		$current = ltrim($current , '/');

		$url .= $current;
		$post = $this->curlFramelessBuildUrl('POST');

		$files = $_FILES;
		$filepath = array();
		if($post) {
			foreach($files as $userfile=>$file) {
				if (is_array($file)) {
					if(is_array($file['name'])) {
						foreach ($file['name'] as $key => $value) {
							$name = $file['name'][$key];
							$path = $file['tmp_name'][$key];
							if ($name) {
								$filepath[$key] = JPATH_ROOT . '/tmp/' . $name;
								rename($path, $filepath[$key]);
								$post[$userfile . '[' . $key . ']'] = '@' . $filepath[$key];
							}
						}
					} else {
						$path = $file['tmp_name'];
						$name = $file['name'];
						$key = $path;
						$filepath[$key] = JPATH_ROOT . '/tmp/' . $name;
						rename($path, $filepath[$key]);
						$post[$userfile] = '@' . $filepath[$key];
					}
				}
			}
		}

		$ch = curl_init($url);
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		} else {
			curl_setopt($ch, CURLOPT_POST, 0);
		}

		if(!empty($data->httpauth) ) {
			curl_setopt($ch,CURLOPT_USERPWD, $data->httpauth_username . ':' . $data->httpauth_password);

			switch ($data->httpauth) {
				case 'basic':
					$data->httpauth = CURLAUTH_BASIC;
					break;
				case 'gssnegotiate':
					$data->httpauth = CURLAUTH_GSSNEGOTIATE;
					break;
				case 'digest':
					$data->httpauth = CURLAUTH_DIGEST;
					break;
				case 'ntlm':
					$data->httpauth = CURLAUTH_NTLM;
					break;
				case 'anysafe':
					$data->httpauth = CURLAUTH_ANYSAFE;
					break;
				case 'any':
				default:
					$data->httpauth = CURLAUTH_ANY;
			}

			curl_setopt($ch,CURLOPT_HTTPAUTH, $data->httpauth);
		}

		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		curl_setopt($ch, CURLOPT_REFERER, $ref);

		$headers = array();
		$headers[] = 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR'];
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlFramelessReadHeader'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_FAILONERROR, 0);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 2 );
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$data->verifyhost = isset($data->verifyhost) ? $data->verifyhost : 2;
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $data->verifyhost);

		curl_setopt($ch, CURLOPT_HEADER, 0);

		$cookies = Factory::getCookies();

		$_COOKIE['jfusionframeless'] = true;
		curl_setopt($ch, CURLOPT_COOKIE, $cookies->buildCookie());
		unset($_COOKIE['jfusionframeless']);

		$data->buffer = curl_exec($ch);

		$this->curlFramelessProtectParams($data);

		if ($this->curlLocation) {
			$data->location = $this->curlLocation;
		}

		$data->cookie_domain = isset($data->cookie_domain) ? $data->cookie_domain : '';
		$data->cookie_path = isset($data->cookie_path) ? $data->cookie_path : '';

		foreach ($this->cookies as $cookie) {
			$cookies->addCookie($cookie->name, urldecode($cookie->value), $cookie->expires, $data->cookie_path, $data->cookie_domain);
		}

		if (curl_errno($ch)) {
			$errorMessage = curl_error($ch);
			curl_close($ch);
			throw new \RuntimeException(Text::_('CURL_ERROR_MSG') . ': ' . $errorMessage . ' URL:' . $url);
		} else {
			curl_close($ch);

			if (count($filepath)) {
				foreach($filepath as $value) {
					unlink($value);
				}
			}
		}
	}

	/**composer
	 * @param $ch
	 * @param $string
	 *
	 * @return int
	 */
	public final function curlFramelessReadHeader($ch, $string) {
		$length = strlen($string);
		if(!strncmp($string, 'Location:', 9)) {
			$this->curlLocation = trim(substr($string, 9, -1));
		} else if(!strncmp($string, 'Set-Cookie:', 11)) {
			$string = trim(substr($string, 11, -1));
			$parts = explode(';', $string);

			list($name, $value) = explode('=', $parts[0]);

			$cookie = new stdClass;
			$cookie->name = trim($name);
			$cookie->value = trim($value);
			$cookie->expires = 0;

			if (isset($parts[1])) {
				list($name, $value) = explode('=', $parts[1]);
				if ($name == 'expires') {
					$cookie->expires = strtotime($value);
				}
			}

			$this->cookies[] = $cookie;
		}
		return $length;
	}

	/**
	 * @param string $type
	 *
	 * @return mixed|string
	 */
	private function curlFramelessBuildUrl($type = 'GET') {
		if ($type == 'POST') {
			$var = $_POST;
		} else {
			$var = $_GET;
		}

		foreach($this->protected as $name) {
			$key = 'jfusion_' . $name;
			if (isset($var[$key])) {
				$var[$name] = $var[$key];
				unset($var[$key]);
			}
		}

		unset($var['Itemid'], $var['option'], $var['view'], $var['jFusion_Route'], $var['jfile']);
		if ($type == 'POST') return $var;
		return http_build_query($var);
	}

	/**
	 * @param stdClass $data
	 */
	private function curlFramelessProtectParams(&$data) {
		$regex_input = array();
		$replace_input = array();

		$uri = new Uri($data->source_url);

		$search = array();
		$search[] = preg_quote($uri->getPath(), '#');
		$search[] = preg_quote($uri->toString(array('scheme', 'host', 'path')), '#');
		$search[] = '(?!\w{0,10}://|\w{0,10}:|\/)';

		foreach($this->protected as $name) {
			$name = preg_quote($name , '#');
			$regex_input[]	= '#<input([^<>]+name=["\'])(' . $name . '["\'][^<>]*)>#Si';
			$replace_input[] = '<input$1jfusion_$2>';

			foreach($search as $type) {
				$regex_input[]	= '#<a([^<>]+href=["\']' . $type . '.*?[\?|\&|\&amp;])(' . $name . '.*?["\'][^<>]*)>#Si';
				$replace_input[] = '<a$1jfusion_$2>';
			}
		}

		foreach ($regex_input as $k => $v) {
			//check if we need to use callback
			$data->buffer = preg_replace($regex_input[$k], $replace_input[$k], $data->buffer);
		}
	}

	/**
	 * Returns Array of stdClass title / url
	 * Array of stdClass with title and url assigned.
	 *
	 * @return array Db columns assigned to title and url links for pathway
	 */
	function getPathWay()
	{
		return array();
	}

	/**
	 * Parses custom BBCode defined in $this->prepareText()
	 *
	 * @param mixed $bbcode
	 * @param int $action
	 * @param string $name
	 * @param string $default
	 * @param mixed $params
	 * @param string $content
	 *
	 * @return mixed bbcode converted to html
	 */
	function parseCustomBBCode($bbcode, $action, $name, $default, $params, $content)
	{
		if ($action == 1) {
			$return = true;
		} else {
			$return = $content;
			switch ($name) {
				case 'size':
					$return = '<span style="font-size:' . $default . '">' . $content . '</span>';
					break;
				case 'glow':
					$temp = explode(',', $default);
					$color = (!empty($temp[0])) ? $temp[0] : 'red';
					$return = '<span style="background-color:' . $color . '">' . $content . '</span>';
					break;
				case 'shadow':
					$temp = explode(',', $default);
					$color = (!empty($temp[0])) ? $temp[0] : '#6374AB';
					$dir = (!empty($temp[1])) ? $temp[1] : 'left';
					$x = ($dir == 'left') ? '-0.2em' : '0.2em';
					$return = '<span style="text-shadow: ' . $color . ' ' . $x . ' 0.1em 0.2em;">' . $content . '</span>';
					break;
				case 'move':
					$return = '<marquee>' . $content . '</marquee>';
					break;
				case 'pre':
					$return = '<pre>' . $content . '</pre>';
					break;
				case 'hr':
					$return = '<hr>';
					break;
				case 'flash':
					$temp = explode(',', $default);
					$width = (!empty($temp[0])) ? $temp[0] : '200';
					$height = (!empty($temp[1])) ? $temp[1] : '200';
					$return = <<<HTML
                        <object classid="clsid:D27CDB6E-AE6D-11CF-96B8-444553540000" codebase="http://active.macromedia.com/flash2/cabs/swflash.cab#version=5,0,0,0" width="{$width}" height="{$height}">
                            <param name="movie" value="{$content}" />
                            <param name="play" value="false" />
                            <param name="loop" value="false" />
                            <param name="quality" value="high" />
                            <param name="allowScriptAccess" value="never" />
                            <param name="allowNetworking" value="internal" />
                            <embed src="{$content}" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/shockwave/download/index.cgi?P1_Prod_Version=ShockwaveFlash" width="{$width}" height="{$height}" play="false" loop="false" quality="high" allowscriptaccess="never" allownetworking="internal">
                            </embed>
                        </object>
HTML;
					break;
				case 'ftp':
					if (empty($default)) {
						$default = $content;
					}
					$return = '<a href="' . $content . '">' . $default . '</a>';
					break;
				case 'table':
					$return = '<table>' . $content . '</table>';
					break;
				case 'tr':
					$return = '<tr>' . $content . '</tr>';
					break;
				case 'td':
					$return = '<td>' . $content . '</td>';
					break;
				case 'tt';
					$return = '<tt>' . $content . '</tt>';
					break;
				case 'o':
				case 'O':
				case '0':
					$return = '<li type="circle">' . $content . '</li>';
					break;
				case '*':
				case '@':
					$return = '<li type="disc">' . $content . '</li>';
					break;
				case '+':
				case 'x':
				case '#':
					$return = '<li type="square">' . $content . '</li>';
					break;
				case 'abbr':
					if (empty($default)) {
						$default = $content;
					}
					$return = '<abbr title="' . $default . '">' . $content . '</abbr>';
					break;
				case 'anchor':
					if (!empty($default)) {
						$return = '<span id="' . $default . '">' . $content . '</span>';
					} else {
						$return = $content;
					}
					break;
				case 'black':
				case 'blue':
				case 'green':
				case 'red':
				case 'white':
					$return = '<span style="color: ' . $name . ';">' . $content . '</span>';
					break;
				case 'iurl':
					if (empty($default)) {
						$default = $content;
					}
					$return = '<a href="' . htmlspecialchars($default) . '" class="bbcode_url" target="_self">' . $content . '</a>';
					break;
				case 'html':
				case 'nobbc':
				case 'php':
					$return = $content;
					break;
				case 'ltr':
					$return = '<div style="text-align: left;" dir="$name">' . $content . '</div>';
					break;
				case 'rtl':
					$return = '<div style="text-align: right;" dir="$name">' . $content . '</div>';
					break;
				case 'me':
					$return = '<div style="color: red;">* ' . $default . ' ' . $content . '</div>';
					break;
				case 'time':
					$return = date('Y-m-d H:i', $content);
					break;
				default:
					break;
			}
		}
		return $return;
	}
}
