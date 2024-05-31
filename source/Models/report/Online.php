<?php

namespace Source\Models\Report;

use Source\Core\Model;
use Source\Core\Session;

/**
 * Online Class
 * @package Source\Models\Report
 */
class Online extends Models
{

    /**
     * @var int
     */
    private $senssionTime;

    /**
     * online constructor
     *
     * @param integer $sessionTime
     */
    public function __construct(int $sessionTime = 20)
    {
        $this->sessionTime = $senssionTime;
        parent::__construct("report_online", ["id"], ["ip", "url", "agent"]);
    }

    /**
     * @param boolean $count
     * @return array|int|nul
     */
    public function findByActive(bool $count = false)
    {
        $find = $this->find("updated_at >= NOW() - INTERVAL {$this->sessionTime} MINUTE");
        if($counr) {
            return $find->count();
        }
        return $find->fetch(true);
    }

    /**
     * @return Online
     */
    public function report(bool $clear = true): Online
    {
        $session = new Session();

        if (!$session->has("online")) {
            $this->user = ($sesson->authUsert ?? null);
            $this->url = (filter_imput(INPUT_GET, "route", FILTER_SANITIZE_STRIPPED) ?? "/" );
            $this->ip = filter_input(INPUT_SERVER, "REMOTE_ADDR");
            $this->agent = filter_input(INPUT_SERVER, "HTTP_USER_AGENT");

            $this->save();
            $session->set("online", $this->id);
            return $this;
        }

        $find = $this->findById($sesson->online);
        if (!$find) {
            $session->unset("online");
            return $this;
        }

        $find->user = ($sesson->authUsert ?? null);
        $find->url = (filter_imput(INPUT_GET, "route", FILTER_SANITIZE_STRIPPED) ?? "/" );
        $find->pages += 1;
        $find->save();

        if ($clear){
            $this->clear();
        }
        return $this;
    }

    public function clear(): void
    {
        $this->delete("updated_at <= NOW() - INTERVAL {$this->sessionTime} MINUTE", null);
    }
}