<?php

/**
 * Smskaufen gateway API class
 * Better avoid implementing this provider:
 *  - Encoding of UTF8 characters issues
 *  - Simple quotes cannot be used in messages
 *  - The lowest pricing is so cheaper that it may have security issues e.g. sending SMS on the behalf of individuals
 * Read https://www.smskaufen.com/sms/downloads/smskaufen-schnittstelle.pdf
 */

namespace Xi\Sms\Gateway;

use Xi\Sms\SmsMessage;
use Xi\Sms\SmsException;
use Carbon\Carbon;

class SmskaufenGateway extends BaseHttpRequestGateway {

	protected $_errorCodes = array(
		'111' => 'IP blocked',
		'112' => 'Wrong credentials (login/pw/apikey)',
		'122' => 'Text is empty',
		'123' => 'Recipient is empty',
		'140' => 'No credit'
	);

	protected $settings = array(
		'username' => '',
		'password' => '',
		'api_http' => 'http://www.smskaufen.com/sms/gateway/',
		'api_https' => 'https://www.smskaufen.com/sms/gateway/',
		'gateway' => 13,
		'https' => false,
	);

	public function getBaseUrl() {
		if ($this->settings['https']) {
			return $this->settings['api_https'];
		}
		return $this->settings['api_http'];
	}

	/**
	 * @param array $settings
	 * @throws RuntimeException Exception.
	 */
	public function __construct($settings = array()) {
		$this->settings = array_merge($this->settings, $settings);

		if (empty($this->settings['username']) || empty($this->settings['password'])) {
			throw new SmsException('Credentials missing');
		}
	}

	public function send(SmsMessage $message)
	{
		return $this->sms($message->getBody(), $message->getTo(), $message->getFrom());
	}

	public function sms($text, $to, $from) {

		if (empty($text)) {
			return false;
		}
		if (strlen($text) > 160 &&
			!in_array($this->settings['gateway'], array(2,3,4,8))) {
			throw new SmsException('Only gateways 2,3,4,8 allow sending messages with more than 160 chars.');
			return false;
		}

		$numbers = (array) $to;
		$params = array(
			'id' => $this->settings['username'],
			'pw' => $this->settings['password'],
			'type' => $this->settings['gateway'],
			'text' => utf8_decode($text),
			'empfaenger' => implode(';', $numbers),
			'absender' => $from,
		);

		if (count($numbers) > 1) {
			$params['massen'] = 1;
			$dt = \Carbon\Carbon::yesterday();
			$dt->setToStringFormat('d.m.Y-00:01');
			$dt->__toString();
			$params['termin'] = $dt->__toString(); # past => immediately
		}

		if (!empty($params['massen']) &&
			!in_array($this->settings['gateway'], array(2,3,4,8))) {
			throw new SmsException('Only gateways 2,3,4,8 allow mass sending');
			return false;
		}

		$res = $this->getClient()->post(
			$this->getBaseUrl() . 'sms.php' ,
			array('Content-type' => 'application/x-www-form-urlencoded'),
			$params
		);

		if (!$res->isOk()) {
			return false;
		}
		$content = $res->getContent();
		if ($content != 100 && $content != 101) {
			return false;
		}
		return true;
	}
}
