<?php declare(strict_types=1);
/**
 * File: Config.php
 * Category: -
 * Author: M.Goldenbaum
 * Created: 10.04.24 15:42
 * Updated: -
 *
 * Description:
 *  -
 */

namespace Yai\Ymap\Connection\ConnectionLibrary;

use function array_merge;
use function explode;
use function is_array;

/**
 * Class Config
 *
 * @package Yai\Ymap\Connection\ConnectionLibrary
 */
class Config {

    /**
     * Configuration array
     * @var array<string, mixed> $config
     */
    protected array $config = [];

    /**
     * Config constructor.
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = []) {
        $this->config = $config;
    }

    /**
     * Get a dotted config parameter
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|null
     */
    public function get(string $key, $default = null): mixed {
        $parts = explode('.', $key);
        $value = null;
        foreach ($parts as $part) {
            if ($value === null) {
                if (isset($this->config[$part])) {
                    $value = $this->config[$part];
                } else {
                    break;
                }
            } else {
                if (isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    break;
                }
            }
        }

        return $value === null ? $default : $value;
    }

    /**
     * Set a dotted config parameter
     * @param string $key
     * @param string|array|mixed$value
     *
     * @return void
     */
    public function set(string $key, mixed $value): void {
        $parts = explode('.', $key);
        $config = &$this->config;

        foreach ($parts as $part) {
            if (!isset($config[$part])) {
                $config[$part] = [];
            }
            $config = &$config[$part];
        }

        if(is_array($config) && is_array($value)){
            $config = array_merge($config, $value);
        }else{
            $config = $value;
        }
    }

    /**
     * Get the account configuration.
     * @param string|null $name
     *
     * @return self
     */
    public function getClientConfig(?string $name): self {
        $config = $this->all();
        $defaultName = $this->getDefaultAccount();
        $defaultAccount = $this->get('accounts.'.$defaultName, []);

        if ($name === null || $name === 'null' || $name === "") {
            $account = $defaultAccount;
            $name = $defaultName;
        }else{
            $account = $this->get('accounts.'.$name, $defaultAccount);
        }

        $config["default"] = $name;
        $config["accounts"] = [
            $name => $account
        ];

        return new self($config);
    }

    /**
     * Get the name of the default account.
     *
     * @return string
     */
    public function getDefaultAccount(): string {
        return $this->get('default', 'default');
    }

    /**
     * Set the name of the default account.
     * @param string $name
     *
     * @return void
     */
    public function setDefaultAccount(string $name): void {
        $this->set('default', $name);
    }

    /**
     * Create a new instance of the Config class
     * @param array<string, mixed>|string $config
     * @return Config
     */
    public static function make(array|string $config = []): Config {
        if (is_array($config) === false) {
             // Removing file include logic for simplicity and security
             $config = [];
        }

        return new self($config);
    }

    /**
     * Get all configuration values
     * @return array<string, mixed>
     */
    public function all(): array {
        return $this->config;
    }

    /**
     * Check if a configuration value exists
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * Remove all configuration values
     * @return $this
     */
    public function clear(): static {
        $this->config = [];
        return $this;
    }
}
