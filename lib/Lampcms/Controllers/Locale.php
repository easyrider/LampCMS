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
use Lampcms\Responder;
use Lampcms\Cookie;
use Lampcms\I18n\Translator;

/**
 * This controller processes "select locale"
 * drop-down form
 * When user selects new value of "language"
 * via the drop-down form at the bottom of pages
 * the request is sent to this controller.
 * It sets the system-wide locale and
 * changes the value of locale in Viewer object
 * and also sends out the locale cookie
 * Then it redirects the user back to the
 * page where they came from
 *
 *
 * @author Dmitri Snytkine
 *
 */
class Locale extends WebPage
{
	protected $aRequired = array('locale', 'redirect');

	protected function main(){
		$locale = $this->oRequest->get('locale');
		/*echo __METHOD__.' '.__LINE__.'$locale: '.$locale;
		 exit;*/

		if(isset($_SESSION['guest_block'])){
			unset($_SESSION['guest_block']);
		}

		if(isset($_SESSION['langs'])){
			unset($_SESSION['langs']);
		}

		if(isset($_SESSION['welcome'])){
			unset($_SESSION['welcome']);
		}

		if(isset($_SESSION['welcome_guest'])){
			unset($_SESSION['welcome_guest']);
		}

		$_SESSION['locale'] = $locale;
		$this->oRegistry->Locale->set($locale);
		Cookie::set('locale', $locale);
		if(!empty($_SESSION['langs'])){
			unset($_SESSION['langs']);
		}
		//echo __METHOD__.' '.__LINE__.' getting Tr object for locale: '.$locale;
		//$this->Tr = Translator::factory($this->oRegistry, $locale);
		//echo __METHOD__.' '.__LINE__.' '.print_r($this->Tr->getMessages(), 1);//$this->Tr->get('Questions');

		Responder::redirectToPage($this->oRequest->get('redirect'));
	}
}
