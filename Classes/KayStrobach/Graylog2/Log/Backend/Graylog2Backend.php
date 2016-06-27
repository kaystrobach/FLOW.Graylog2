<?php

namespace KayStrobach\Graylog2\Log\Backend;

use Gelf\Logger;
use Gelf\Publisher;
use Gelf\Transport\TcpTransport;
use Gelf\Transport\UdpTransport;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Http\HttpRequestHandlerInterface;
use TYPO3\Flow\Log\Backend\AbstractBackend;
use TYPO3\Flow\Object\ObjectManagerInterface;


class Graylog2Backend extends AbstractBackend {

    /**
     * Graylog2 host
     * @var string
     */
    protected $host = null;

    /**
     * Graylog2 port
     * @var string
     */
    protected $port = null;

    /**
     * Graylog2 chunksize
     * @var string
     */
    protected $chunksize = null;

    /**
     * Graylog2 transport either udp or tcp
     * @var string
     */
    protected $transport = null;

    /**
     * @var Logger
     */
    protected $logger = null;


    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param string $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getChunksize()
    {
        return $this->chunksize;
    }

    /**
     * @param string $chunksize
     */
    public function setChunksize($chunksize)
    {
        $this->chunksize = $chunksize;
    }

    /**
     * @return string
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param string $transport
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
    }

    /**
     * Carries out all actions necessary to prepare the logging backend, such as opening
     * the log file or opening a database connection.
     *
     * @return void
     * @api
     */
    public function open()
    {
        if (!isset($this->host) || strlen($this->host) === 0) {
            return;
        }

        $host = $this->host;
        $port = isset($this->port) ? $this->port : UdpTransport::DEFAULT_PORT;
        // set chunk size option to wan (default) or lan
        if (isset($this->chunksize) && strtolower($this->chunksize) === 'lan') {
            $chunkSize = UdpTransport::CHUNK_SIZE_LAN;
        } else {
            $chunkSize = UdpTransport::CHUNK_SIZE_WAN;
        }
        // setup connection to graylog2 server
        switch (strtolower($this->transport)) {
            case 'udp':
                $transport = new UdpTransport($host, $port, $chunkSize);
                break;
            case 'tcp':
            default:
                $transport = new TcpTransport($host, $port, $chunkSize);
                break;
        }

        $publisher = new Publisher();
        $publisher->addTransport($transport);
        $this->logger = new Logger($publisher);
    }

    /**
     * Appends the given message along with the additional information into the log.
     *
     * @param string $message The message to log
     * @param integer $severity One of the LOG_* constants
     * @param mixed $additionalData A variable containing more information about the event to be logged
     * @param string $packageKey Key of the package triggering the log (determined automatically if not specified)
     * @param string $className Name of the class triggering the log (determined automatically if not specified)
     * @param string $methodName Name of the method triggering the log (determined automatically if not specified)
     * @return void
     * @api
     */
    public function append($message, $severity = LOG_INFO, $additionalData = null, $packageKey = null, $className = null, $methodName = null)
    {
        if($this->logger === null) {
            return;
        }
        $messageContext = array(
            'additionalData' => $additionalData,
            'packageKey' => $packageKey,
            'className' => $className,
            'methodName' => $methodName
        );

        // prepare request details
        if (Bootstrap::$staticObjectManager instanceof ObjectManagerInterface) {
            $bootstrap = Bootstrap::$staticObjectManager->get('TYPO3\Flow\Core\Bootstrap');
            /* @var Bootstrap $bootstrap */
            $requestHandler = $bootstrap->getActiveRequestHandler();
            if ($requestHandler instanceof HttpRequestHandlerInterface) {
                $request = $requestHandler->getHttpRequest();
                $requestData = array(
                    'request_domain' => $request->getHeader('Host'),
                    'request_remote_addr' => $request->getClientIpAddress(),
                    'request_path' => $request->getRelativePath(),
                    'request_uri' => $request->getUri()->getPath(),
                    'request_user_agent' => $request->getHeader('User-Agent'),
                    'request_method' => $request->getMethod(),
                    'request_port' => $request->getPort()
                );
                $messageContext = array_merge($messageContext, $requestData);
            }
        }

        try {
            $this->logger->log($severity, $message, $messageContext);
        } catch (\RuntimeException $e) {
            // do nothing, as we can't log here anymore :(
        }

    }

    /**
     * Carries out all actions necessary to cleanly close the logging backend, such as
     * closing the log file or disconnecting from a database.
     *
     * @return void
     * @api
     */
    public function close()
    {
        // nothing to do here
    }
}