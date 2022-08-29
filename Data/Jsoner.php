<?php

namespace Posts_Jsoner\Data;

use Mosquitto\Exception;
use \Posts_Jsoner\Storage\FileSystem;
use \Posts_Jsoner\Storage\S3Wrapper;

class Jsoner
{
    private FileSystem $filesystem;
    private S3Wrapper $s3wrapper;
    private array $data;

    private string $country;
    private string $language;
    private int $postId;

    /**
     * Jsoner constructor.
     *
     * @param string $country
     * @param string $language
     * @param int $postId
     */
    public function __construct(string $country, string $language, int $postId)
    {
        $environment = \Post_Jsoner_Admin::getActiveSiteEnvironment();
        $this->country = $country;
        $this->language = $language;
        $this->postId = $postId;
        $this->filesystem = new FileSystem();
        try {
            $this->s3wrapper = new S3Wrapper($environment);
        } catch (\Exception $e) {
            \error_log("S3Wrapper error: ".$e->getMessage(),3,'/tmp/wp-errors.log');
        }
    }

    /**
     * @param object $post
     *
     * @return bool
     */
    public function updateNode(object $post): bool
    {
        try {
            $this->loadFromFile($post->post_type);
            $nodeId = $this->findByColumn($this->data, $post->ID, 'ID');
            $mapper = MapperFactory::getMapper(JSONER_MAPPER);
            $template = $mapper->getTemplate($post->post_type);
            error_log(var_export($template,1),3,'/tmp/wp-errors.log');
            $normalizedCustom = $mapper->reformatCustoms($post->ID, $post->post_type);
            $mappedPost = $mapper->map($post, $template, $normalizedCustom);
            if ($nodeId >= 0) { // updates existing node
                $this->data[$nodeId] = $mappedPost;
            } else { // creates new node
                $this->data[] = $mappedPost;
            }
            $this->saveToFile($this->data, $post->post_type);

            if ((!empty($this->s3wrapper) && S3Wrapper::checkConnection())) {
                // Upload to S3 bucket
                $filename = S3Wrapper::genFilename($this->country, $this->language, $post->post_type . ".json");
                $this->s3wrapper->uploadFile(JSONER_EXPORT_PATH . $filename, $filename);
            }
        } catch (\Exception $e) {
            error_log($e->getMessage(),3,'/tmp/wp-errors.log');
            return false;
        }
        return true;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function loadFromFile(string $type = 'posts'): bool
    {
        try {
            $prefix = 'post_jsoner_';
            $type = json_decode(get_option($prefix.$type, '{}'),1);
            if (empty($type) || (false === $type['enabled'])) {
                return false;
            }
            $this->data = $this->filesystem->loadFromJson($this->country, $this->language, $type['value']) ?? [];
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return true;
    }

    // lookup

    /**
     * Search a post/page based on single column search
     *
     * @param array  $list
     * @param string $needle
     * @param string $column
     *
     * @return int
     */
    public function findByColumn(array $list, string $needle, string $column): int
    {
        $key = array_search($needle, array_column($list, $column));
        return (false === $key)
            ? -1
            : $key;
    }

    // update

    /**
     * @param array  $data
     * @param string $type
     *
     * @return bool
     */
    public function saveToFile(array $data, string $type = 'posts'): bool
    {
        try {
            $prefix = 'post_jsoner_';
            $type = json_decode(get_option($prefix.$type, '{}'),1);
            if (empty($type) || (false === $type['enabled'])) {
                return false;
            }
            $result = $this->filesystem->saveToJson($this->country, $this->language, $data, $type);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
        return $result;
    }
}