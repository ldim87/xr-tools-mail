<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

use \PHPMailer;

class Mail
{
	protected $dbg;
	
	protected $config = [];

	function __construct(
		\XrTools\Utils\DebugMessages $dbg,
		array $config = []
	){
		$this->dbg = $dbg;
		$this->config = $config;
	}

	/**
	 * Send mail to admin
	 * @param  string $subject Subject
	 * @param  string $message Message body
	 * @return boolean         Mail status
	 */
	function sendToAdmin($subject, $message){
		return mail(
			$this->config['admin_mail_to'] ?? 'admin@localhost',
			$subject,
			$message,
			'From: ' . ($this->config['admin_mail_from'] ?? 'admin@localhost')
		);
	}
	
	/**
	 * Send mail to user (by smtp)
	 * @param string $to      Recipient e-mail address
	 * @param string $name    Mail subject
	 * @param string $info    Mail body text
	 * @param array  $sys     Settings with options:
	 *                        <ul>
	 *                        <li> <strong> debug </strong> boolean
	 *                        - Debug mode (log errors). Default: false
	 *                        <li> <strong> from_name </strong> string
	 *                        - FROM name. Default: "Хрумка"
	 *                        <li> <strong> from_mail </strong> string
	 *                        - FROM mail. Default: "info@{$_SERVER['SERVER_NAME']}"
	 *                        <li> <strong> reply_to </strong> string
	 *                        - Reply-To header. Default: $sys['from_mail']
	 *                        <li> <strong> return_path </strong> string
	 *                        - Return-Path header. Default: $sys['from_mail']
	 *                        <li> <strong> html </strong> boolean
	 *                        - Send e-mail in HTML format. Default: false (plain text)
	 *                        <li> <strong> returnResultObject </strong> bool
	 *                        - Return object type ['status'=>..., 'message'=>...]. Default: false (return status)
	 *                        </ul>
	 * @return bool|array    Result status | ['status'=>..., 'message'=>...]
	 */
	function send(string $to, string $name, string $info, array $sys = []){
		
		$debug = !empty($sys['debug']);

		if(empty($to) || empty($name) || empty($info)){
			
			if($debug){
				$missing = [];
				if(empty($to))   $missing[] = 'to';
				if(empty($name)) $missing[] = 'name';
				if(empty($info)) $missing[] = 'info';

				$this->dbg->log('Mandatory input param empty: '.implode(', ', $missing), __METHOD__);
			}

			return false;
		}

		if(empty($sys['from_name'])){
			$sys['from_name'] = $this->config['from_name_default'] ?? 'Noreply';
		}
		if(empty($sys['from_mail'])){
			$sys['from_mail'] = $this->config['from_mail_default'] ?? 'noreply@localhost';
		}
		if(empty($sys['reply_to'])){
			$sys['reply_to'] = $sys['from_mail'];
		}
		if(empty($sys['return_path'])){
			$sys['return_path'] = $sys['from_mail'];
		}
		if(empty($sys['sender'])){
			$sys['sender'] = $sys['return_path'];
		}
		if(!isset($sys['html'])){
			$sys['html'] = false;
		}

		try {
			$mail = new PHPMailer;

			$mail->CharSet = 'UTF-8';
			$mail->From = $sys['from_mail'];
			$mail->addReplyTo($sys['reply_to']);

			// bounce
			$mail->ReturnPath = $sys['return_path'];
			$mail->Sender = $sys['sender'];

			$mail->FromName = $sys['from_name'];
			$mail->addAddress($to);

			if($sys['html']){
				$mail->WordWrap = 50;
				$mail->isHTML(true);
			}

			if(!empty($sys['bulk_unsubscribe'])){
				$mail->addCustomHeader('Precedence', 'bulk');
				$mail->addCustomHeader('List-Unsubscribe', $sys['bulk_unsubscribe']);
			}

			$mail->Subject = $name;
			$mail->Body = $info;

			if(!empty($sys['alt_body'])){
				// This is the body in plain text for non-HTML mail clients
				$mail->AltBody = $sys['alt_body'];
			}

			// Use SMTP
			$mail->IsSMTP();

			$mail->Host = $this->config['smtp_host'] ?? 'localhost';

			if(!empty($this->config['smtp_auth_user'])){
				$mail->SMTPAuth = true;

				$mail->Username = $this->config['smtp_auth_user'];
				$mail->Password = $this->config['smtp_auth_password'] ?? '';

				$mail->Port = $this->config['smtp_port'] ?? 587;

				// force TLS
				$mail->SMTPSecure = 'tls';
				$mail->SMTPAutoTLS = true;

			} else {
				$mail->SMTPAuth = false;

				$mail->Port = $this->config['smtp_port'] ?? 25;

				// disable TLS
				$mail->SMTPSecure = '';
				$mail->SMTPAutoTLS = false;
			}

			// enables SMTP debug information (for testing)
			if($debug){
				$mail->SMTPDebug = 2;
			}

			if($mail->send()){
				$status = true;
				$message = '';

				if($debug){
					$this->dbg->log('Message sent', __METHOD__);
				}
			}
			else {
				$status = false;
				$message = $mail->ErrorInfo;

				if($debug){
					$this->dbg->log('Error: ' . $message, __METHOD__);
				}
			}
		}
		catch(\Exception $e){
			$status = false;
			$message = 'PHPMailer error: ' . $e->getMessage();
		}

		return !empty($sys['err_info']) ? ['status' => $status, 'message' => $message] : $status;
	}
}
