<?php

class Downloader {
    /** @var string */
    private $source = null;

    /** @var string */
    private $output_file = 'output';

    /** @var int */
    private $chunk_quantity = 4;

    /** @var int */
    private $chunk_size_bytes = 1048576; // 1 MiB = 1048576 bytes

    /** @var bool */
    private $verbose_output = false;

    /** @var bool */
    private $download_complete = false;

    /**
     * @param array $argv Command line arguments
     *
     * @return void
     */
    public function execute($argv) {
        // Usage: php downloader --source (source URL) [--output_file (output file)]
        if ($this->process_arguments($argv)) {
            $this->download_file();
        }
    }

    /**
     * Applies command line arguments and options.
     *
     * @param array $arguments Command line arguments
     *
     * @return boolean or void
     */
    private function process_arguments($arguments) {
        // Require source argument
        $source_index = array_search('--source', $arguments);
        if ($source_index && isset($arguments[$source_index + 1])) {
            $this->source = $arguments[$source_index + 1];
        } else {
            echo "Please specify source URL with parameter --source (source URL).\r\n";
            return false;
        }

        // Process optional output file argument
        $output_file_index = array_search('--output_file', $arguments);
        if ($output_file_index && isset($arguments[$output_file_index + 1])) {
            $this->output_file = $arguments[$output_file_index + 1];
        }

        // Process optional chunk quantity option
        $chunk_quantity_index = array_search('--chunks', $arguments);
        if ($chunk_quantity_index && isset($arguments[$chunk_quantity_index + 1])) {
            $this->chunk_quantity = $arguments[$chunk_quantity_index + 1];
        }

        // Process optional chunk size option
        $chunk_size_index = array_search('--chunk_size', $arguments);
        if ($chunk_size_index && isset($arguments[$chunk_size_index + 1])) {
            $this->chunk_size_bytes = $arguments[$chunk_size_index + 1];
        }

        // Get requested file size so we can handle files smaller than chunks * chuck_size
        $requested_file_size = $this->curl_get_file_size($this->source);
        if ($requested_file_size > -1) {
            $this->requested_file_size = $this->curl_get_file_size($this->source);
        } else {
            echo "File not found. Please specify source URL with parameter --source (source URL).\r\n";
            return false;
        }

        // Process optional verbose output option
        if (array_search('-v', $arguments)) {
            $this->verbose_output = true;
        }

        return true;
    }

    /**
     * Downloads file using parallel curl requests.
     *
     * @return void
     */
    private function download_file() {
        // User output
        echo "Downloading from URL " . $this->source . "\r\n";
        echo "File size: " . $this->requested_file_size . "\r\n";

        // Create multiple curl handle for parallel retrieval
        $mh = curl_multi_init();

        // Create curl handle for each chunk
        for ($index = 0; $index < $this->chunk_quantity; $index++) {
            ${'ch' . $index} = $this->init_curl_handler($index);
            curl_multi_add_handle($mh,${'ch' . $index});
        }

        // Execute all requests simultaneously and continue when all are complete
        $running = null;
        do {
            curl_multi_exec($mh, $running);
        } while ($running);

        // Write results to file
        for ($index = 0; $index < $this->chunk_quantity; $index++) {
            // Open file
            if ($index > 0) {
                // Open file for writing and append to existing file
                $fp = fopen("./" . $this->output_file, "a");
            } else {
                // Open file for writing and overwrite if file is pre-existing
                $fp = fopen("./" . $this->output_file, "w");
            }

            // Write current chunk to file
            $response = curl_multi_getcontent(${'ch'.$index});
            fwrite($fp, $response);
            echo "Writing chunk " . ($index+1) . " to file " . $this->output_file . "\r\n";
            
            // Clean up
            fclose($fp);
            curl_multi_remove_handle($mh, ${'ch' . $index});
        }

        // Let user know everything completed normally
        echo "All done!\r\n";

        // Clean up 
        curl_multi_close($mh);
    }

    /**
     * Initializes curl handler.
     *
     * @param int $index Current chunk index
     *
     * @return Curl handler
     */
    private function init_curl_handler($index) {
        // Establish chunk range of this requst
        $range_from = $index     * $this->chunk_size_bytes;
        $range_to   = ($index+1) * $this->chunk_size_bytes - 1;

        // Optional: Support files smaller than 4 MiB (less chunks/adjust chunk size)
        if ($this->requested_file_size - ($index * $this->chunk_size_bytes - 1) < $this->chunk_size_bytes) {
            // Update range to so that we don't exceed file size
            $range_to = $range_from + ($this->requested_file_size - ($index * $this->chunk_size_bytes));
            
            // Update number of chunks to bail out early
            $this->chunk_quantity = $index + 1;
        }

        // User output
        echo "Chunk " . ($index + 1) . " size: " . (($range_to - $range_from) + 1) . "\r\n";

        // Init
        $ch = curl_init();

        // Set URL
        curl_setopt($ch, CURLOPT_URL, $this->source);

        // Set range for this GET
        curl_setopt($ch, CURLOPT_RANGE, $range_from .'-' . $range_to);

        // Return response as string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Verbose output to show request headers
        // Useful for verifying range headers sent by each request
        if ($this->verbose_output) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        return $ch;
    }

    /**
     * Adapted from https://stackoverflow.com/questions/2602612/php-remote-file-size-without-downloading-file
     *
     * Returns the size of a file without downloading it, or -1 if the file
     * size could not be determined.
     *
     * @param string $url Location of the remote file to download
     *
     * @return The size of the file referenced by $url, or -1 if the size
     * could not be determined.
     */
    private function curl_get_file_size($url) {
        // Init
        $result = -1;
        $curl   = curl_init($url);

        // Issue a HEAD request and follow any redirects.
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $data = curl_exec($curl);
        curl_close($curl);

        if($data) {
            $content_length = "unknown";
            $status = "unknown";

            if(preg_match("/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches)) {
                $status = (int)$matches[1];
            }

            if(preg_match("/Content-Length: (\d+)/", $data, $matches)) {
                $content_length = (int)$matches[1];
            }

            // http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
            if($status == 200 || ($status > 300 && $status <= 308)) {
                $result = $content_length;
            }
        }

        return $result;
    }
}

// Invoke class and pass in command line arguments
(new Downloader)->execute($argv);

?>