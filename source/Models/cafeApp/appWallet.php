<?php

namespace Source\Models\cafeApp;

use Source\Core\Model;
use Source\Models\User;

class appWallet extends Model
{
    public function __construct()
    {
        parent::__construct("app_wallets", ["id"], ["user_id", "wallwt"]);
    }

    public function start(user $user): AppWallet
    {
        if (!$this->find("user_id = :user", "user={$user->id}")->count()) {
            $this->user_id = $user->id;
            $this->wallet = "Minha Carteira";
            $this->save();
        }
        return $this;
    }
}
