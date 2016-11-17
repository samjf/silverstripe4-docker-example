<?php

namespace SilverStripe\Control\Email;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;
use SilverStripe\View\Requirements;
use SilverStripe\View\ViewableData;
use SilverStripe\View\ViewableData_Customised;

if(isset($_SERVER['SERVER_NAME'])) {
	/**
	 * X-Mailer header value on emails sent
	 */
	define('X_MAILER', 'SilverStripe Mailer - version 2006.06.21 (Sent from "'.$_SERVER['SERVER_NAME'].'")');
} else {
	/**
	 * @ignore
	 */
	define('X_MAILER', 'SilverStripe Mailer - version 2006.06.21');
}

/**
 * Class to support sending emails.
 */
class Email extends ViewableData {

	/**
	 * @var string $from Email-Address
	 */
	protected $from;

	/**
	 * @var string $to Email-Address. Use comma-separation to pass multiple email-addresses.
	 */
	protected $to;

	/**
	 * @var string $subject Subject of the email
	 */
	protected $subject;

	/**
	 * Passed straight into {@link $ss_template} as $Body variable.
	 *
	 * @var string $body HTML content of the email.
	 */
	protected $body;

	/**
	 * If not set, defaults to converting the HTML-body with {@link Convert::xml2raw()}.
	 *
	 * @var string $plaintext_body Optional string for plaintext emails.
	 */
	protected $plaintext_body;

	/**
	 * @var string $cc
	 */
	protected $cc;

	/**
	 * @var string $bcc
	 */
	protected $bcc;

	/**
	 * @var array $customHeaders A map of header-name -> header-value
	 */
	protected $customHeaders = array();

	/**
	 * @var array $attachments Internal, use {@link attachFileFromString()} or {@link attachFile()}
	 */
	protected $attachments = array();

	/**
	 * @var boolean $parseVariables_done
	 */
	protected $parseVariables_done = false;

	/**
	 * @var string $ss_template The name of the used template (without *.ss extension)
	 */
	protected $ss_template = 'GenericEmail';

	/**
	 * Used in the same way than {@link ViewableData->customize()}.
	 *
	 * @var ViewableData_Customised $template_data Additional data available in a template.
	 */
	protected $template_data;

	/**
	 * This will be set in the config on a site-by-site basis
	 *
	 * @config
	 * @var string The default administrator email address.
	 */
	private static $admin_email = '';

	/**
 	 * Send every email generated by the Email class to the given address.
 	 *
	 * It will also add " [addressed to (email), cc to (email), bcc to (email)]" to the end of the subject line
	 *
	 * To set this, set Email.send_all_emails_to in your yml config file.
	 * It can also be set in _ss_environment.php with SS_SEND_ALL_EMAILS_TO.
	 *
	 * @config
	 * @var string $send_all_emails_to Email-Address
	 */
	private static $send_all_emails_to;

	/**
	 * Send every email generated by the Email class *from* the given address.
	 * It will also add " [, from to (email)]" to the end of the subject line
	 *
	 * To set this, set Email.send_all_emails_from in your yml config file.
	 * It can also be set in _ss_environment.php with SS_SEND_ALL_EMAILS_FROM.
	 *
	 * @config
	 * @var string $send_all_emails_from Email-Address
	 */
	private static $send_all_emails_from;

	/**
	 * @config
	 * @var string BCC every email generated by the Email class to the given address.
	 */
	private static $bcc_all_emails_to;

	/**
	 * @config
	 * @var string CC every email generated by the Email class to the given address.
	 */
	private static $cc_all_emails_to;

	/**
	 * Create a new email.
	 *
	 * @param string|null $from
	 * @param string|null $to
	 * @param string|null $subject
	 * @param string|null $body
	 * @param string|null $bounceHandlerURL
	 * @param string|null $cc
	 * @param string|null $bcc
	 */
	public function __construct($from = null, $to = null, $subject = null, $body = null, $bounceHandlerURL = null,
			$cc = null, $bcc = null) {

		if($from !== null) $this->from = $from;
		if($to !== null) $this->to = $to;
		if($subject !== null) $this->subject = $subject;
		if($body !== null) $this->body = $body;
		if($cc !== null) $this->cc = $cc;
		if($bcc !== null) $this->bcc = $bcc;

		if($bounceHandlerURL !== null) {
			Deprecation::notice('4.0', 'Use "emailbouncehandler" module');
		}

		parent::__construct();
	}

	/**
	 * Get the mailer.
	 *
	 * @return Mailer
	 */
	public static function mailer() {
		return Injector::inst()->get('SilverStripe\\Control\\Email\\Mailer');
	}

	/**
	 * Attach a file based on provided raw data.
	 *
	 * @param string $data The raw file data (not encoded).
	 * @param string $attachedFilename Name of the file that should appear once it's sent as a separate attachment.
	 * @param string|null $mimeType MIME type to use when attaching file. If not provided, will attempt to infer via HTTP::get_mime_type().
	 * @return $this
	 */
	public function attachFileFromString($data, $attachedFilename, $mimeType = null) {
		$this->attachments[] = array(
			'contents' => $data,
			'filename' => $attachedFilename,
			'mimetype' => $mimeType,
		);
		return $this;
	}

	/**
	 * Attach the specified file to this email message.
	 *
	 * @param string $filename Relative or full path to file you wish to attach to this email message.
	 * @param string|null $attachedFilename Name of the file that should appear once it's sent as a separate attachment.
	 * @param string|null $mimeType MIME type to use when attaching file. If not provided, will attempt to infer via HTTP::get_mime_type().
	 * @return $this
	 */
	public function attachFile($filename, $attachedFilename = null, $mimeType = null) {
		if(!$attachedFilename) $attachedFilename = basename($filename);
		$absoluteFileName = Director::getAbsFile($filename);
		if(file_exists($absoluteFileName)) {
			$this->attachFileFromString(file_get_contents($absoluteFileName), $attachedFilename, $mimeType);
		} else {
			user_error("Could not attach '$absoluteFileName' to email. File does not exist.", E_USER_NOTICE);
		}
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function Subject() {
		return $this->subject;
	}

	/**
	 * @return string|null
	 */
	public function Body() {
		return $this->body;
	}

	/**
	 * @return string|null
	 */
	public function To() {
		return $this->to;
	}

	/**
	 * @return string|null
	 */
	public function From() {
		return $this->from;
	}

	/**
	 * @return string|null
	 */
	public function Cc() {
		return $this->cc;
	}

	/**
	 * @return string|null
	 */
	public function Bcc() {
		return $this->bcc;
	}

	/**
	 * @param string $val
	 * @return $this
	 */
	public function setSubject($val) {
		$this->subject = $val;
		return $this;
	}

	/**
	 * @param string $val
	 * @return $this
	 */
	public function setBody($val) {
		$this->body = $val;
		return $this;
	}

	/**
	 * @param string $val
	 * @return $this
	 */
	public function setTo($val) {
		$this->to = $val;
		return $this;
	}

	/**
	 * @param string $val
	 * @return $this
	 */
	public function setFrom($val) {
		$this->from = $val;
		return $this;
	}

	/**
	 * @param string $val
	 * @return $this
	 */
	public function setCc($val) {
		$this->cc = $val;
		return $this;
	}

	/**
	 * @param string $val
	 * @return $this
	 */
	public function setBcc($val) {
		$this->bcc = $val;
		return $this;
	}

	/**
	 * Set the "Reply-To" header with an email address.
	 *
	 * @param string $val
	 * @return $this
	 */
	public function setReplyTo($val) {
		$this->addCustomHeader('Reply-To', $val);
		return $this;
	}

	/**
	 * Add a custom header to this email message. Useful for implementing all those cool features that we didn't think of.
	 *
	 * IMPORTANT: If the specified header already exists, the provided value will be appended!
	 *
	 * @todo Should there be an option to replace instead of append? Or maybe a new method ->setCustomHeader()?
	 *
	 * @param string $headerName
	 * @param string $headerValue
	 * @return $this
	 */
	public function addCustomHeader($headerName, $headerValue) {
		if ($headerName == 'Cc') {
			$this->cc = $headerValue;
		} elseif($headerName == 'Bcc') {
			$this->bcc = $headerValue;
		} else {
			// Append value instead of replacing.
			if(isset($this->customHeaders[$headerName])) {
				$this->customHeaders[$headerName] .= ", " . $headerValue;
			} else {
				$this->customHeaders[$headerName] = $headerValue;
			}
		}
		return $this;
	}

	/**
	 * @return string
	 */
	public function BaseURL() {
		return Director::absoluteBaseURL();
	}

	/**
	 * Get an HTML string for debugging purposes.
	 *
	 * @return string
	 */
	public function debug() {
		$this->parseVariables();

		return "<h2>Email template $this->class</h2>\n" .
			"<p><b>From:</b> $this->from\n" .
			"<b>To:</b> $this->to\n" .
			"<b>Cc:</b> $this->cc\n" .
			"<b>Bcc:</b> $this->bcc\n" .
			"<b>Subject:</b> $this->subject</p>" .
			$this->body;
	}

	/**
	 * Set template name (without *.ss extension).
	 *
	 * @param string $template
	 * @return $this
	 */
	public function setTemplate($template) {
		$this->ss_template = $template;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTemplate() {
		return $this->ss_template;
	}

	/**
	 * @return Email|ViewableData_Customised
	 */
	protected function templateData() {
		if($this->template_data) {
			return $this->template_data->customise(array(
				"To" => $this->to,
				"Cc" => $this->cc,
				"Bcc" => $this->bcc,
				"From" => $this->from,
				"Subject" => $this->subject,
				"Body" => $this->body,
				"BaseURL" => $this->BaseURL(),
				"IsEmail" => true,
			));
		} else {
			return $this;
		}
	}

	/**
	 * Used by {@link SSViewer} templates to detect if we're rendering an email template rather than a page template
	 */
	public function IsEmail() {
		return true;
	}

	/**
	 * Populate this email template with values. This may be called many times.
	 *
	 * @param array|ViewableData $data
	 * @return $this
	 */
	public function populateTemplate($data) {
		if($this->template_data) {
			$this->template_data = $this->template_data->customise($data);
		} else {
			if(is_array($data)) $data = new ArrayData($data);
			$this->template_data = $this->customise($data);
		}
		$this->parseVariables_done = false;

		return $this;
	}

	/**
	 * Load all the template variables into the internal variables, including
	 * the template into body.	Called before send() or debugSend()
	 * $isPlain=true will cause the template to be ignored, otherwise the GenericEmail template will be used
	 * and it won't be plain email :)
	 *
	 * @param bool $isPlain
	 * @return $this
	 */
	protected function parseVariables($isPlain = false) {
		$origState = SSViewer::config()->get('source_file_comments');
		SSViewer::config()->update('source_file_comments', false);

		if(!$this->parseVariables_done) {
			$this->parseVariables_done = true;

			// Parse $ variables in the base parameters
			$this->templateData();

			// Process a .SS template file
			$fullBody = $this->body;
			if($this->ss_template && !$isPlain) {
				// Requery data so that updated versions of To, From, Subject, etc are included
				$data = $this->templateData();
				$candidateTemplates = [
					$this->ss_template,
					[ 'type' => 'email', $this->ss_template ]
				];
				$template = new SSViewer($candidateTemplates);
				if($template->exists()) {
					$fullBody = $template->process($data);
				}
			}

			// Rewrite relative URLs
			$this->body = HTTP::absoluteURLs($fullBody);
		}
		SSViewer::config()->update('source_file_comments', $origState);

		return $this;
	}

	/**
	 * Send the email in plaintext.
	 *
	 * @see send() for sending emails with HTML content.
	 * @uses Mailer->sendPlain()
	 *
	 * @param string $messageID Optional message ID so the message can be identified in bounces etc.
	 * @return mixed Success of the sending operation from an MTA perspective. Doesn't actually give any indication if
	 * the mail has been delivered to the recipient properly). See Mailer->sendPlain() for return type details.
	 */
	public function sendPlain($messageID = null) {
		Requirements::clear();

		$this->parseVariables(true);

		if(empty($this->from)) $this->from = Email::config()->admin_email;

		$headers = $this->customHeaders;

		if($messageID) $headers['X-SilverStripeMessageID'] = project() . '.' . $messageID;

		if(project()) $headers['X-SilverStripeSite'] = project();

		$to = $this->to;
		$from = $this->from;
		$subject = $this->subject;
		if($sendAllTo = $this->config()->send_all_emails_to) {
			$subject .= " [addressed to $to";
			$to = $sendAllTo;
			if($this->cc) $subject .= ", cc to $this->cc";
			if($this->bcc) $subject .= ", bcc to $this->bcc";
			$subject .= ']';
			unset($headers['Cc']);
			unset($headers['Bcc']);
		} else {
			if($this->cc) $headers['Cc'] = $this->cc;
			if($this->bcc) $headers['Bcc'] = $this->bcc;
		}

		if($ccAllTo = $this->config()->cc_all_emails_to) {
			if(!empty($headers['Cc']) && trim($headers['Cc'])) {
				$headers['Cc'] .= ', ' . $ccAllTo;
			} else {
				$headers['Cc'] = $ccAllTo;
			}
		}

		if($bccAllTo = $this->config()->bcc_all_emails_to) {
			if(!empty($headers['Bcc']) && trim($headers['Bcc'])) {
				$headers['Bcc'] .= ', ' . $bccAllTo;
			} else {
				$headers['Bcc'] = $bccAllTo;
			}
		}

		if($sendAllfrom = $this->config()->send_all_emails_from) {
			if($from) $subject .= " [from $from]";
			$from = $sendAllfrom;
		}

		Requirements::restore();

		return self::mailer()->sendPlain($to, $from, $subject, $this->body, $this->attachments, $headers);
	}

	/**
	 * Send an email with HTML content.
	 *
	 * @see sendPlain() for sending plaintext emails only.
	 * @uses Mailer->sendHTML()
	 *
	 * @param string $messageID Optional message ID so the message can be identified in bounces etc.
	 * @return mixed Success of the sending operation from an MTA perspective. Doesn't actually give any indication if
	 * the mail has been delivered to the recipient properly). See Mailer->sendPlain() for return type details.
	 */
	public function send($messageID = null) {
		Requirements::clear();

		$this->parseVariables();

		if(empty($this->from)) $this->from = Email::config()->admin_email;

		$headers = $this->customHeaders;

		if($messageID) $headers['X-SilverStripeMessageID'] = project() . '.' . $messageID;

		if(project()) $headers['X-SilverStripeSite'] = project();


		$to = $this->to;
		$from = $this->from;
		$subject = $this->subject;
		if($sendAllTo = $this->config()->send_all_emails_to) {
			$subject .= " [addressed to $to";
			$to = $sendAllTo;
			if($this->cc) $subject .= ", cc to $this->cc";
			if($this->bcc) $subject .= ", bcc to $this->bcc";
			$subject .= ']';
			unset($headers['Cc']);
			unset($headers['Bcc']);

		} else {
			if($this->cc) $headers['Cc'] = $this->cc;
			if($this->bcc) $headers['Bcc'] = $this->bcc;
		}


		if($ccAllTo = $this->config()->cc_all_emails_to) {
			if(!empty($headers['Cc']) && trim($headers['Cc'])) {
				$headers['Cc'] .= ', ' . $ccAllTo;
			} else {
				$headers['Cc'] = $ccAllTo;
			}
		}

		if($bccAllTo = $this->config()->bcc_all_emails_to) {
			if(!empty($headers['Bcc']) && trim($headers['Bcc'])) {
				$headers['Bcc'] .= ', ' . $bccAllTo;
			} else {
				$headers['Bcc'] = $bccAllTo;
			}
		}

		if($sendAllfrom = $this->config()->send_all_emails_from) {
			if($from) $subject .= " [from $from]";
			$from = $sendAllfrom;
		}

		Requirements::restore();

		return self::mailer()->sendHTML($to, $from, $subject, $this->body, $this->attachments, $headers,
			$this->plaintext_body);
	}

	/**
	 * Validates the email address to get as close to RFC 822 compliant as possible.
	 *
	 * @param string $email
	 * @return bool
	 *
	 * @copyright Cal Henderson <cal@iamcal.com>
	 * 	This code is licensed under a Creative Commons Attribution-ShareAlike 2.5 License
	 * 	http://creativecommons.org/licenses/by-sa/2.5/
	 */
	public static function is_valid_address($email){
		$qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
		$dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
		$atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c'.
			'\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
		$quoted_pair = '\\x5c[\\x00-\\x7f]';
		$domain_literal = "\\x5b($dtext|$quoted_pair)*\\x5d";
		$quoted_string = "\\x22($qtext|$quoted_pair)*\\x22";
		$domain_ref = $atom;
		$sub_domain = "($domain_ref|$domain_literal)";
		$word = "($atom|$quoted_string)";
		$domain = "$sub_domain(\\x2e$sub_domain)*";
		$local_part = "$word(\\x2e$word)*";
		$addr_spec = "$local_part\\x40$domain";

		return preg_match("!^$addr_spec$!", $email) === 1;
	}

	/**
	 * Encode an email-address to help protect it from spam bots. At the moment only simple string substitutions, which
	 * are not 100% safe from email harvesting.
	 *
	 * @todo Integrate javascript-based solution
	 *
	 * @param string $email Email-address
	 * @param string $method Method for obfuscating/encoding the address
	 *	- 'direction': Reverse the text and then use CSS to put the text direction back to normal
	 *	- 'visible': Simple string substitution ('@' to '[at]', '.' to '[dot], '-' to [dash])
	 *	- 'hex': Hexadecimal URL-Encoding - useful for mailto: links
	 * @return string
	 */
	public static function obfuscate($email, $method = 'visible') {
		switch($method) {
			case 'direction' :
				Requirements::customCSS(
					'span.codedirection { unicode-bidi: bidi-override; direction: rtl; }',
					'codedirectionCSS'
				);
				return '<span class="codedirection">' . strrev($email) . '</span>';
			case 'visible' :
				$obfuscated = array('@' => ' [at] ', '.' => ' [dot] ', '-' => ' [dash] ');
				return strtr($email, $obfuscated);
			case 'hex' :
				$encoded = '';
				for ($x=0; $x < strlen($email); $x++) $encoded .= '&#x' . bin2hex($email{$x}).';';
				return $encoded;
			default:
				user_error('Email::obfuscate(): Unknown obfuscation method', E_USER_NOTICE);
				return $email;
		}
	}
}
