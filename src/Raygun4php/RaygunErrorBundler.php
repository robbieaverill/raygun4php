<?php 

namespace Raygun4php
{
  class RaygunErrorBundler {
    private $bundle = array();
    private $settings = array();
    private $startTime;
    private $storageFile;
    private $writeToDisk;

    public function __construct($options = array()) {
      $defaults = array(
        "storageFile" => realpath(__DIR__ . '/raygun_error_bundle.txt'),
        "maxBundleSize" => 100,
        "expiryInSeconds" => 60,
        "gzipBundle" => false,
        "gzipLevel" => 6,
        "encodeData" => true,
        "writeToDisk" => false
      );

      $this->settings = array_merge($defaults, $options);

      $this->startTime = time();
      $this->storageFile = $this->settings["storageFile"];
      $this->writeToDisk = $this->settings["writeToDisk"];
    }

    public function getBundle() {
      return $this->bundle;
    }

    public function setBundle($bundle = array()) {
      $this->bundle = $bundle;
    }

    public function addMessage($message) {
      $this->bundle[] = $message;
      $this->setInStorage($message);
    }

    public function getJson() {
      $bundle = $this->getBundle();

      // Manually convert to JSON to prevent double-encoding
      $bundleString = implode(",", $bundle);
      $bundleString = "[{$bundleString}]";

      if($this->settings["gzipBundle"]) {
        $bundleString = gzencode($bundleString, $this->settings["gzipLevel"]);
      }

      if($this->settings["encodeData"]) {
        $bundleString = base64_encode($bundleString);
      }

      return $bundleString;
    }

    public function reset() {
      $this->startTime = time();
      $this->setBundle(array());
      $this->resetStorage();

    }

    public function isReadyToSend() {
      return count($this->getBundle()) >= $this->settings["maxBundleSize"] || $this->isBundleExpired();
    }

    public function getFromStorage() {
      $storageFile = $this->storageFile;
      $sessionBundle = array();

      if($this->writeToDisk && file_exists($storageFile) && is_readable($storageFile)) {
        $contents = file_get_contents($storageFile);

        if(!empty($contents)) {
          $sessionBundle = explode("\n", $contents);
        }
      }
      else if(isset($_SESSION) && !empty($_SESSION["raygun_error_bundle"])) {
        $sessionBundle = $_SESSION["raygun_error_bundle"];
      }

      // Remove empty items from array
      $sessionBundle = array_filter($sessionBundle);

      return $sessionBundle;
    }

    private function setInStorage($message) {
      $storageFile = $this->storageFile;

      // Save to disk to disk if possible
      if($this->writeToDisk && file_exists($storageFile) && is_writable($storageFile)) {
        return file_put_contents($storageFile, "{$message}\n", FILE_APPEND);
      }
      // Else store in session global
      else if(isset($_SESSION)){
        if(!isset($_SESSION["raygun_error_bundle"])) {
          $_SESSION["raygun_error_bundle"] = array();
        }

        $_SESSION["raygun_error_bundle"][] = $message;

        return true;
      }
    }

    private function resetStorage() {
      $storageFile = $this->storageFile;

      if($this->writeToDisk && file_exists($storageFile) && is_writable($storageFile)) {
        return file_put_contents($storageFile, "");
      }
      else if(isset($_SESSION)) {
        $_SESSION["raygun_error_bundle"] = array();
        return true;
      }
    }

    private function isBundleExpired() {
      return (time() - $this->startTime) > $this->settings["expiryInSeconds"];
    }
  }
}