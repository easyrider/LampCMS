<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\Controllers;

use Lampcms\WebPage;
use Lampcms\User;
use Lampcms\Request;
use Lampcms\Responder;

class Delete extends WebPage
{
	/**
	 *
	 * Subject of email sent
	 * to moderators
	 *  @todo translate string
	 */
	const SUBJECT = 'Request to delete question';

	/**
	 *
	 * Body of email sent to moderators
	 * when request to delete is made

	 * @todo stranslate string
	 */
	const EMAIL_BODY = '
	User: %1$s
	Profile: %2$s
	
	Requesting to delete question: %3$s
	
	Title: %4$s
	
	Reason: %5$s
	
	';


	const USER_DETAILS = '<p>This user posted<br>
	<strong>%1$s</strong> Question(s)<br>
	<strong>%2$s</strong> Answer(s)
	<br>User profile: <a href="%3$s">%4$s</a><br>';

	protected $membersOnly = true;

	protected $requireToken = true;

	protected $aRequired = array('rid', 'rtype');

	/**
	 * Resource being deleted
	 * either Question or Answer
	 *
	 * @var object
	 */
	protected $oResource;

	/**
	 * Collection name, usually
	 * either QUESTIONS and ANSWERS
	 *
	 * @var string
	 */
	protected $collection;

	/**
	 * Flag indicates that request to delete
	 * item has been sent to moderators
	 * This indicates that item has not yet been deleted
	 * but instead it was a request to delete
	 *
	 * @var bool
	 */
	protected $requested = false;



	protected $oCache;

	/**
	 *
	 * Extra info about number of
	 * questions and answers the same poster
	 * already made
	 * It will be shown if delete action is
	 * done by moderator so that moderator
	 * can quickly navigate to poster's profile
	 * and then possibly delete other posts
	 * by the same user
	 *
	 * @var string
	 */
	protected $posterDetails;


	protected function main(){
		/**
		 * Need to instantiate Cache so that it
		 * will listen to event and unset some keys
		 */
		$this->oCache = $this->oRegistry->Cache;
		$this->collection = ('q' == $this->oRequest['rtype']) ? 'QUESTIONS' : 'ANSWERS';
		$this->permission = ('QUESTIONS' === $this->collection) ? 'delete_question' : 'delete_answer';

		$this->getResource()
		->checkPermission()
		->setDeleted()
		->updateQuestion()
		->getUserData()
		->banUser()
		->returnResult();
	}


	/**
	 * Is viewer is moderator then
	 * we add extra details about the poster
	 * who's question or answer was just deleted
	 * This way after item is deleted moderator
	 * can quickly navigate to poster's profile
	 * page and possibly delete other items
	 * and maybe ban user too
	 *
	 * @return object $this
	 */
	protected function getUserData(){

		if($this->oRegistry->Viewer->isModerator()){
			$uid = $this->oResource['i_uid'];
			$oUser = \Lampcms\User::factory($this->oRegistry)->by_id($uid);
			$q = $this->oRegistry->Mongo->QUESTIONS->find(array('i_uid' => $uid, 'i_del_ts' => null));
			$a = $this->oRegistry->Mongo->ANSWERS->find(array('i_uid' => $uid, 'i_del_ts' => null));

			$this->posterDetails = vsprintf(self::USER_DETAILS,
			array(
			$q->count(),
			$a->count(),
			$oUser->getProfileUrl(),
			$oUser->getDisplayName())
			);

		}

		return $this;
	}


	/**
	 * If answer is deleted and it was
	 * the "selected" answer then
	 * we must update question and set it as
	 * 'unanswered' again
	 *
	 * @return object $this;
	 */
	protected function updateQuestion(){
		if(('ANSWERS' === $this->collection)){

			$oQuestion = new \Lampcms\Question($this->oRegistry);
			$oQuestion->by_id($this->oResource['i_qid']);
			$oQuestion->updateAnswerCount(-1);

			if((true === $this->oResource['accepted'])){
				d('this was an accepted answer');

				$this->oResource['accepted'] = false;
				$oQuestion->offsetUnset('i_sel_ans');
			}

			$oQuestion->save();
		}

		return $this;
	}


	/**
	 *
	 * Change role of Question or Answer poster
	 * to 'Banned'
	 *
	 * @return $this;
	 *
	 */
	protected function banUser(){
		$ban = $this->oRequest->get('ban', 's', '');
		d('ban: '.$ban);

		if(!empty($ban) && $this->checkAccessPermission('ban_user')){
			$oUser = User::factory($this->oRegistry)->by_id($this->oResource->getOwnerId());
			$oUser->offsetSet('role', 'suspended');
			$oUser->save();

			$this->oRegistry->Dispatcher->post($oUser, 'onUserBanned');
		}

		return $this;
	}



	/**
	 * Delete is allowed by Resource Owner OR
	 * by user authorized to delete_question
	 * or delete_answer in the acl
	 *
	 * Also if question has answer(s) then
	 * even the owner is not allowed to delete
	 * and instead the moderators are notified
	 *
	 * @return object $this
	 */
	protected function checkPermission(){

		if($this->oRegistry->Viewer->getUid() != $this->oResource->getOwnerId()){

			$this->checkAccessPermission($this->permission);
		}

		return $this;
	}


	/**
	 * If deleting a question and it has answers
	 * and Viewer does not have permission to
	 * delete_question then all we do is notify
	 * admins about the request to delete, they will
	 * handle it.
	 *
	 * Otherwise we mark items as deleted
	 *
	 * @return object $this
	 */
	protected function setDeleted(){
		if(('QUESTIONS' === $this->collection)
		&& ($this->oResource->getAnswerCount() > 0) ){
			try{
				$this->checkAccessPermission();
			} catch (\Lampcms\AccessException $e){
				d('not allowed to delete answered question');

				return $this->requestDelete();
			}
		}

		$this->oRegistry->Dispatcher->post($this->oResource, 'onBeforeResourceDelete');

		/**
		 * Important! run updateTags()
		 * BEFORE setting item as deleted
		 * because updateTags() will ignore
		 * Question that is marked as deleted
		 *
		 */
		$this->updateTags();
		$this->oResource->setDeleted($this->oRegistry->Viewer, $this->oRequest['note']);

		d('new resource data: '.print_r($this->oResource->getArrayCopy(), 1));

		$this->oRegistry->Dispatcher->post($this->oResource, 'onResourceDelete');

		return $this;
	}


	/**
	 * Now update tags counter to decrease tags count
	 * but ONLY if deleted item is Question
	 *
	 * @return object $this;
	 */
	protected function updateTags(){

		if(!$this->requested){
			if('QUESTIONS' === $this->collection){
				$oQuestion = $this->oResource;

				\Lampcms\Qtagscounter::factory($this->oRegistry)->removeTags($oQuestion);
				if(0 === $this->oResource['i_sel_ans']){
					d('going to remove to Unanswered tags');
					\Lampcms\UnansweredTags::factory($this->oRegistry)->remove($oQuestion);
				}
			} else {
				$oQuestion = new \Lampcms\Question($this->oRegistry);
				$oQuestion->by_id($this->oResource['i_qid']);
				d('tags: ' . print_r($oQuestion['a_tags'], 1));
			}

			/**
			 * Must extract uid from $this->oResource because in case
			 * the resource is an answer, then the
			 * $oQuestion has different owner, thus
			 * will remove user tags for the wrong user
			 *
			 */
			$uid = $this->oResource->getOwnerId();
			\Lampcms\UserTags::factory($this->oRegistry)->removeTags($oQuestion, $uid);

		}

		return $this;
	}


	/**
	 * Email request for deleting item
	 * to moderators.
	 * This happends if viewer is owner of question
	 * that has at least one answer
	 * but not a moderator, therefor does not
	 * have permission to delete such question
	 *
	 * @return object $this
	 */
	protected function requestDelete(){
		$cur = $this->oRegistry->Mongo->USERS->find(array(
  			'role' => array('$in' => array('moderator', 'admin'))
		), array('email'));

		d('found '.$cur->count().' moderators');

		if($cur && $cur->count() > 0){
			$Mailer = Mailer::factory($this->oRegistry);
			$subject = self::SUBJECT;
			$body = $this->makeBody();
			register_shutdown_function(function() use ($cur, $Mailer, $subject, $body){
				try{
					foreach($cur as $row){
						$Mailer->mail($row['email'], $subject, $body);
					}
				} catch (\Exception $e){
					e('Unable to send email to moderator: '.$e->getMessage());
				}
			});
		}

		$this->requested = true;

		return $this;
	}


	/**
	 * Make body of the email
	 * which will be sent to moderators
	 *
	 * @return string body of email
	 */
	protected function makeBody(){
		$vars = array(
		$this->oRegistry->Viewer->getDisplayName(),
		$this->oRegistry->Ini->SITE_URL.$this->oRegistry->Viewer->getProfileUrl(),
		$this->oResource->getUrl(),
		$this->oResource['title'],
		$this->oRequest['note']
		);

		d('vars: '.print_r($vars, 1));

		$body = vsprintf(self::EMAIL_BODY, $vars);

		d('body '.$body);

		return $body;
	}


	/**
	 * Create object of type Question or Answer
	 *
	 * @return object $this
	 */
	protected function getResource(){

		d('type: '.$this->collection);
		$coll = $this->oRegistry->Mongo->getCollection($this->collection);
		$a = $coll->findOne(array('_id' => (int)$this->oRequest['rid']));
		d('a: '.print_r($a, 1));

		if(empty($a)){

			throw new \Lampcms\Exception('Item not found');
		}

		/**
		 * Very important to NOT proceed any further
		 * if item already deleted because deleting
		 * the same item twice with cause problems
		 * with count of tags and stuff like that -
		 * counts will get out of sync
		 */
		if(!empty($a['i_del_ts'])){
			throw new \Lampcms\Exception('This item was already deleted on '.date('r', $a['i_del_ts']));
		}

		$class = ('QUESTIONS' === $this->collection) ? '\\Lampcms\\Question' : '\\Lampcms\\Answer';

		$this->oResource = new $class($this->oRegistry, $a);

		return $this;
	}


	protected function returnResult(){
		/**
		 * @todo translate string
		 */
		$message = 'Item deleted';
		$requested = 'You cannot delete question that already has answers.<br>A request to delete
		this question has been sent to moderators<br>
		It will be up to moderators to either delete or edit or close the question';

		if(Request::isAjax()){
			$res = (!$this->requested) ? $message : $requested;
			$ret = array('alert' => $res);
			if(!empty($this->posterDetails)){
				$ret['alert'] .= $this->posterDetails;
			} else {
				/**
				 * If item was actually deleted then
				 * add 'reload' => 2 to return
				 * which will cause page reload
				 * in 1.5 seconds.
				 */
				if(!$this->requested){
					$ret['reload'] = 1500;
				}
			}

			Responder::sendJSON($ret);
		}

		Responder::redirectToPage($this->oResource->getUrl());
	}
}