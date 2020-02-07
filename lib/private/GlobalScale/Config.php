<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\GlobalScale;


use OCP\GlobalScale\IConfig as IGlobalScaleConfig;
use OCP\IConfig;

class Config implements IGlobalScaleConfig {

	/** @var IConfig */
	private $config;

	/**
	 * Config constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * check if global scale is enabled
	 *
	 * @return bool
	 * @since 12.0.1
	 */
	public function isGlobalScaleEnabled() {
		$enabled = $this->config->getSystemValue('gs.enabled', false);

		return $enabled !== false;
	}

	/**
	 * check if federation should only be used internally in a global scale setup
	 *
	 * @param string $type since 19.0.0
	 *
	 * @return bool
	 * @since 12.0.1
	 */
	public function onlyInternalFederation(string $type) {
		// if global scale is disabled federation works always globally
		$gsEnabled = $this->isGlobalScaleEnabled();
		if ($gsEnabled === false) {
			return false;
		}

		$enabled = $this->config->getSystemValue('gs.federation', 'internal');

		$type = strtolower($type);
		$typeEnabled = 'external';
		if (in_array($type, [IGlobalScaleConfig::INCOMING, IGlobalScaleConfig::OUTGOING])) {
			$typeEnabled = $this->config->getSystemValue('gs.federation.' . $type, 'internal');
		}

		return $enabled === 'internal' || $typeEnabled === 'internal';
	}


	/**
	 * @param string $remote
	 * @param string $token
	 * @param string $key
	 *
	 * @return bool
	 * @since 19.0.0
	 */
	public function allowedOutgoingFederation(string $remote, string $token = '', string $key = ''): bool {
		if (!$this->onlyInternalFederation(self::OUTGOING)) {
			return true;
		}

		\OC::$server->getLogger()
					->log(
						3, '### allowedOutgoingFederation/remote: ' . $remote . '/token:' . $token . '/key: '
						   . $key
					);


		if ($key !== '' && $token !== '') {
			return $this->keyIsInternal($token, $key);
		}

		return $this->remoteIsInternal($remote);
	}


	/**
	 * @param string $token
	 * @param string $key
	 * @param string $remote
	 *
	 * @return bool
	 * @since 19.0.0
	 */
	public function allowedIncomingFederation(string $token, string $key, string $remote = ''): bool {
		if (!$this->onlyInternalFederation(self::INCOMING)) {
			return true;
		}

		\OC::$server->getLogger()
					->log(
						3, '### allowedIncomingFederation/token:' . $token . '/key: ' . $key . '/remote: '
						   . $remote
					);

		if ($remote !== '') {
			return $this->remoteIsInternal($remote);
		}

		return $this->keyIsInternal($token, $key);
	}


	/**
	 * @param string $token
	 *
	 * @return string
	 */
	public function generateInternalKey(string $token): string {
		$jwt = $this->config->getSystemValue('gss.jwt.key', '');
		if ($jwt === '') {
			return '';
		}

		$key = '##' . $token . '-123';
		\OC::$server->getLogger()
					->log(3, 'Manager/generateInternalKey/jwt:' . $jwt . '/token:' . $token . '/key:' . $key);

		return $key;
	}


	/**
	 * @param string $remote
	 *
	 * @return bool
	 */
	public function remoteIsInternal(string $remote): bool {
		if (!$this->isGlobalScaleEnabled()) {
			return false;
		}

		// TODO - request the lookup server for the list of valid instance
		$internals = ['gs2.local'];
		if (in_array($remote, $internals)) {
			return true;
		}

		return false;
	}


	/**
	 * @param string $token
	 * @param string $key
	 *
	 * @return bool
	 */
	private function keyIsInternal(string $token, string $key): bool {
		if ($key === '##' . $token . '-123') {
			return true;
		}

		return false;
	}

}

