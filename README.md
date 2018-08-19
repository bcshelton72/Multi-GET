# Multi-GET

This script downloads part of a file from a web server in chunks.

The script verifies the specified URL exists, outputs the file size, and downloads the file using parallel curl requests. The number of parallel requests defaults to 4 but can be specified. Similarly the size of each request chunk defaults to 1 MiB but can be specified in bytes.

The script gracefully handles the case of downloading files smaller than the specified (or defaulted) amount.

### Prerequisites

* [PHP 7.0](http://php.net/)

### Getting Started

Clone the repository to your system.

### Usage
```
php downloader.php  --source <URL of file to download>
```

### Options
* --source: URL of file to download
* --output_file: name of output file (default: "output")
* --chunks: maximum number of chunks to download (default: 4)
* --chunk_size: maximum size of each chunk in bytes (default: 1048576)
* -v: verbose output, useful for verifying range headers sent by each request

### Tests
Baseline usage, defaults to 4 chunks of 1MB each. Output written to file "output":
```
php downloader.php  --source http://bef8f4b1.bwtest-aws.pravala.com/384MB.jar
```

Baseline usage with output file specified:
```
php downloader.php  --source http://bef8f4b1.bwtest-aws.pravala.com/384MB.jar --output_file myoutputfile
```

Customize number of chunks and/or chunk size:
```
php downloader.php  --source http://bef8f4b1.bwtest-aws.pravala.com/384MB.jar --chunks 8 --chunk_size 10000
```

Handle files smaller than 4Mib:
```
php downloader.php  --source <URL of file < 4Mib>
```

Handle files smaller than chunks * chunk_size
```
php downloader.php  --source http://bef8f4b1.bwtest-aws.pravala.com/384MB.jar --chunks 400
```

## Author

* **Brad Shelton** - [bcshelton72@gmail.com](mailto:bcshelton72@gmail.com)

## Acknowledgments

* One bit of functionality was adapted from the Stack Overflow post [PHP: Remote file size without downloading file](https://stackoverflow.com/questions/2602612/php-remote-file-size-without-downloading-file)
