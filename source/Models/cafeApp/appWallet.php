<?php

namespace Source\Models\cafeApp;

class appWallet extends Model
{
    public function __construct()
    {
        parent::__construct("app_wallets", ["id"], ["user_id", "wallwt"]);
    }
}
