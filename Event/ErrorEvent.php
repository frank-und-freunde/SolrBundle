<?php
namespace FS\SolrBundle\Event;

class ErrorEvent extends Event
{

    /**
     * @var \Exception
     */
    private $exception = null;

    /**
     * The exception will be rethrown if this flag is false after dispatch
     *
     * @var bool
     */
    private $handled = false;

    /**
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param \Exception $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @return string
     */
    public function getExceptionMessage()
    {
        if (!$this->exception) {
            return '';
        }

        return $this->exception->getMessage();
    }

    /**
     * Mark the event as handled and do not rethrow an exception
     *
     * @param boolean $handled
     */
    public function setHandled($handled)
    {
        $this->handled = $handled;
    }


    /**
     * @return bool
     */
    public function wasHandled()
    {
        return $this->handled;
    }
}

