<?php

/**
 * @author Oleg Isaev (PandCar)
 * @contacts vk.com/id50416641, t.me/pandcar, github.com/pandcar
 */

namespace XrTools;

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
	 * @param array  $opts     Settings with options:
	 *                        <ul>
	 *                        <li> <strong> debug </strong> boolean
	 *                        - Debug mode (log errors). Default: false
	 *                        <li> <strong> from_name </strong> string
	 *                        - FROM name. Default: "Хрумка"
	 *                        <li> <strong> from_mail </strong> string
	 *                        - FROM mail. Default: "info@{$_SERVER['SERVER_NAME']}"
	 *                        <li> <strong> reply_to </strong> string
	 *                        - Reply-To header. Default: $opts['from_mail']
	 *                        <li> <strong> return_path </strong> string
	 *                        - Return-Path header. Default: $opts['from_mail']
	 *                        <li> <strong> html </strong> boolean
	 *                        - Send e-mail in HTML format. Default: false (plain text)
	 *                        <li> <strong> returnResultObject </strong> bool
	 *                        - Return object type ['status'=>..., 'message'=>...]. Default: false (return status)
	 *                        </ul>
	 * @return bool|array    Result status | ['status'=>..., 'message'=>...]
	 */
    function send($to, $name, $info, $opts = array()){
		$opts = $this->prepareOptions($opts);
		
		// Need $to, $name, $info to send mail
    	if(empty($to) || empty($name) || empty($info)){
            if($opts['debug']){
                $this->dbg->log('Mandatory param is missing: 1:' . !empty($to) . ', 2:' . !empty($name) . ', 3:' . !empty($info), __FUNCTION__);
            }
            return false;
        }
    	
		$status  = false;
		$message = '';
  
		try{
			$mail = new \PHPMailer;
		
			$mail->CharSet = 'UTF-8';
			$mail->From    = $opts['from_mail'];
			$mail->addReplyTo($opts['reply_to']);
		
			// bounce
			$mail->ReturnPath = $opts['return_path'];
			$mail->Sender     = $opts['sender'];
		
			$mail->FromName = $opts['from_name'];
			$mail->addAddress($to);
		
			if($opts['html']){
				$mail->WordWrap = 50;
				$mail->isHTML(true);
			}
		
			if( !empty($opts['bulk_unsubscribe'])){
				$mail->addCustomHeader('Precedence', 'bulk');
				$mail->addCustomHeader('List-Unsubscribe', $opts['bulk_unsubscribe']);
			}
		
			$mail->Subject = $name;
			$mail->Body    = $info;
		
			// $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
		
			// Use SMTP
			$mail->IsSMTP();
		
			$mail->Host = $this->config['smtp_host'] ?? 'localhost';
		
			if( !empty($this->config['smtp_auth_user'])){
				$mail->SMTPAuth = true;
			
				$mail->Username = $this->config['smtp_auth_user'];
				$mail->Password = $this->config['smtp_auth_password'] ?? '';
			
				$mail->Port = $this->config['smtp_port'] ?? 587;
			
				// force TLS
				$mail->SMTPSecure  = 'tls';
				$mail->SMTPAutoTLS = true;
			
			}else{
				$mail->SMTPAuth = false;
			
				$mail->Port = $this->config['smtp_port'] ?? 25;
			
				// disable TLS
				$mail->SMTPSecure  = '';
				$mail->SMTPAutoTLS = false;
			}
		
			// enables SMTP debug information (for testing)
			if($opts['debug']){
				$mail->SMTPDebug = \SMTP::DEBUG_SERVER;
			}
		
			if($mail->send()){
				$status  = true;
				$message = '';
			
				if($opts['debug']){
					$this->dbg->log('Message sent', __FUNCTION__);
				}
			}else{
				$status  = false;
				$message = $mail->ErrorInfo;
			
				if($opts['debug']){
					$this->dbg->log('Error: ' . $message, __FUNCTION__);
				}
			}
		
		}catch(\Exception $e){
			$message = 'Error class PHPMailer: ' . $e->getMessage();
		}

        return !empty($opts['returnResultObject']) ? ['status' => $status, 'message' => $message] : $status;
    }
	
	/**
	 * Prepare options to send mail
	 * If the parameter is not present, it is taken from the configuration, and if it is not, then by default
	 * @param array $opts Options:
	 *                    <ul>
	 *                    	<li>@var string from_name
	 *                    	<li>@var string from_mail
	 *                    	<li>@var string reply_to
	 *                    	<li>@var string return_path
	 *                    	<li>@var string sender
	 *                    	<li>@var bool html
	 *                    	<li>@var bool debug
	 *                    </ul>
	 * @return array
	 */
	private function prepareOptions(array $opts = []){
		$opts['debug'] = !empty($opts['debug']);
		$opts['html']  = !empty($opts['html']);
		
		$opts['from_name']   = $opts['from_name']	?? ( $this->config['from_name']		?? $_SERVER['SERVER_NAME']			);
		$opts['from_mail']   = $opts['from_mail']	?? ( $this->config['from_mail']		?? "info@{$_SERVER['SERVER_NAME']}"	);
		$opts['reply_to']    = $opts['reply_to']	?? ( $this->config['reply_to']		?? $opts['from_mail']				);
		$opts['return_path'] = $opts['return_path']	?? ( $this->config['return_path']	?? $opts['from_mail']				);
		$opts['sender']      = $opts['sender']		?? ( $this->config['sender']		?? $opts['return_path']				);
		
		return $opts;
	}
}
