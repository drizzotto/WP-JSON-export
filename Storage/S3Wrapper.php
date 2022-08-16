<?php

namespace Posts_Jsoner\Storage;

require_once PLUGIN_DIR . DIRECTORY_SEPARATOR . 'aws.phar';

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;

class S3Wrapper
{
    private S3Client $client;
    private string $bucket;
    private string $key;
    private string $secret;
    private string $region;
    private string $environment;

    /**
     * S3Wrapper constructor.
     *
     * @param string $env
     * @throws \Exception
     */
    public function __construct(string $env = 'qa')
    {

        $this->environment = $env;

        $this->key = \Post_Jsoner_S3_Config::getAccessKey($this->environment);
        $this->secret = \Post_Jsoner_S3_Config::getSecretKey($this->environment);
        $this->bucket = \Post_Jsoner_S3_Config::getBucketValue($this->environment);
        $this->region = \Post_Jsoner_S3_Config::getRegion($this->environment);

        if (empty($this->key) || empty($this->secret) || empty($this->region)) {
            throw new \Exception("Invalid AWS credentials");
        }

        $credentials = new Credentials($this->key, $this->secret);
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => $credentials
        ]);
    }

    /**
     * @param string $filename
     * @param string $targetPath
     */
    public function uploadFile(string $filename, string $targetPath): void
    {
        try {
            $env = empty($this->environment) ? 'stage' : $this->environment;
            error_log("EV: ".var_export($env,1)."\n",3,'/tmp/wp-errors.log');
//            $keySrc = $env . $targetPath;
            $keySrc = \Post_Jsoner_S3_Config::getPathValue($this->environment) . $targetPath;
            error_log("KS: {$keySrc}\n",3,'/tmp/wp-errors.log');


            $args = [
                'Bucket' => $this->bucket,
                'Key' => $keySrc,
                'SourceFile' => $filename,
            ];
            error_log("FN: {$filename}\n",3,'/tmp/wp-errors.log');
            error_log("TP: {$targetPath}\n",3,'/tmp/wp-errors.log');
            error_log("AR: ".var_export($args,1)."\n",3,'/tmp/wp-errors.log');

            $result = $this->client->putObject($args);
            error_log(var_export($result,1),3,'/tmp/wp-errors.log');
        } catch (S3Exception $e) {
            error_log($e->getMessage(),3,'/tmp/wp-errors.log');
        }
    }

    /**
     * @param string $source
     * @param string $target
     */
    public function uploadDirectory(string $source, string $target): void
    {
        try {
            $keySrc = \Post_Jsoner_S3_Config::getPathValue($this->environment) . $target;
            $this->client->uploadDirectory($source, $this->bucket, $keySrc);
        } catch (\Exception $e) {
            error_log("uploadDirectory: ". $e->getMessage(),3,'/tmp/wp-errors.log');
        }
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public function downloadFile(string $filename): string
    {
        $result = $this->client->getObject(
            [
                'Bucket' => $this->bucket,
                'Key' => DIRECTORY_SEPARATOR . $this->environment . $filename
            ]
        );
        $stream = $result->get('stream');
        return file_get_contents($stream);
    }

    /**
     * @param string $country
     * @param string $lang
     * @param string $filename
     *
     * @return string
     */
    public static function genFilename(string $country, string $lang, string $filename): string
    {
        return DIRECTORY_SEPARATOR . $country . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Checks if it can creates
     *
     * @return bool
     */
    public static function checkConnection(): bool
    {
        try {
            $s3Client = new S3Wrapper(WP_SITE_ENV);
            $buckets = $s3Client->client->listBuckets();
            unset($s3Client);
            return !empty($buckets);
        }
        catch(\Exception $e) {
            error_log("S3Wrapper::checkConnection->".$e->getMessage());
            unset($s3Client);
            return false;
        }
    }
}
