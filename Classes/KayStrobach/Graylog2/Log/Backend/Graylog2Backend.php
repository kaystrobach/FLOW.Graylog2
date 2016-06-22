<?php

namespace KayStrobach\Graylog2\Log\Backend;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Http\HttpRequestHandlerInterface;
use TYPO3\Flow\Log\Backend\AbstractBackend;
use TYPO3\Flow\Object\ObjectManagerInterface;


class LogentriesBackend extends AbstractBackend {

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
     * @var Logger
     */
    protected $logger = null;

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
        $transport = new UdpTransport($host, $port, $chunkSize);
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

        $this->logger->log($severity, $message, $messageContext);
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
        // TODO: Implement close() method.
    }
}