<?php

namespace Source\App;

use Source\Core\Controller;
use Source\Core\Session;
use Source\Core\View;
use Source\Models\Auth;
use Source\Models\CafeApp\AppCategory;
use Source\Models\CafeApp\AppInvoice;
use Source\Models\CafeApp\AppWallet;
use Source\Models\Post;
use Source\Models\Report\Access;
use Source\Models\Report\Online;
use Source\Models\User;
use Source\Support\Email;
use Source\Support\Thumb;
use Source\Support\Upload;

/**
 * Class App
 * @package Source\App
 */
class App extends Controller
{
    /** @var User */
    private $user;

    /**
     * App constructor.
     */
    public function __construct()
    {
        parent::__construct(__DIR__ . "/../../themes/" . CONF_VIEW_APP . "/");

        if (!$this->user = Auth::user()) {
            $this->message->warning("Efetue login para acessar o APP.")->flash();
            redirect("/entrar");
        }

        (new Access())->report();
        (new Online())->report();

        (new AppWallet())->start($this->user);
        (new appInvoice())->fixed($this->user, 3);

        //UNCOMFIRMED EMAIL 
        if ($this->user->status != "confirmed") {
            $session = new Session();
            if (!$session->has("appconfirmed")) {
                $this->message->info("Acesse seu email para confirmar seu cadastro")->flash();
                $session->set("appconfirmed", true);
                (new Auth())->register($this->user);
            }
        }
    }

    /**
     * APP HOME
     */
    public function home()
    {
        $head = $this->seo->render(
            "Olá {$this->user->first_name}. Vamos controlar? - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url(),
            theme("/assets/images/share.jpg"),
            false
        );

        echo $this->view->render("home", [
            "head" => $head
        ]);
    }

    /**
     * filter function
     *
     * @param ARRAY $data
     * @return void
     */
    public function filter(ARRAY $data)
    {
        $status (!empty($data["status"]) ? $data["status"] : "all");
        $category (!empty($data["category"]) ? $data["category"] : "all");
        $date (!empty($data["date"]) ? $data["date"] : date("m/y"));

        list($m, $y) = explode("/", $date);
        $m =($m >= 1 && $m <= 12 ? $m : date("m"));
        $y = ($y <= date("y", strtotime("+10year")) ? $y : date("Y",strtotime("+10year")));

        $start = new \DateTime(date("Y-m-t"));
        $end = new \DateTime(date("Y-m-t", strtotime("{$y}-{$m}+1month")));
        $diff + $start->diff($end);

        if($diff->invert) {
            $afterMonths = (floor($diff->days / 30));
            (new AppInvoice())->fixed($this->user, $afterMonths);
        }

        $redirect = ($data["filter"] == "income" ? "receber" : "pagar");
        $json["redirect"] = url("/app/{$redirect}/{$status}/{$category}/{$m}-{$y}");
        echo json_encode($json);    
    }

   /**
    * income function
    *
    * @param array|null $data
    * @return void
    */
    public function income(?array $data): void
    {
        $head = $this->seo->render(
            "Minhas receitas - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url(),
            theme("/assets/images/share.jpg"),
            false
        );
        
        $categories = (new appCategory())
        ->find("type = :t", "t=income", "id, name")
        ->order("order_by, name")
        ->fetch("true");

        echo $this->view->render("invoices", [
            "user" => $this->user,
            "head" => $head,
            "type" => "income",
            "categories" => $categories,
            "invoices" => (new appInvoices())->filter($this->user, "income", ($data ?? null)),
            "filter" => (object) [
                "status"=>($data["status"] ?? null),
                "category"=>($data["category"] ?? null),
                "date"=>(!empty($data["date"]) ? str_replace("-", "/", $data["date"]) : null)
            ],
        ]);
    }

    /**
     * expense function
     *
     * @param array|null $data
     * @return void
     */
    public function expense(?array $data): void 
    {
        $head = $this->seo->render(
            "Minhas despesas - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url(),
            theme("/assets/images/share.jpg"),
            false
        );

        $categories = (new appCategory())
        ->find("type = :t", "t=expense", "id, name")
        ->order("order_by, name")
        ->fetch("true");

        echo $this->view->render("invoices", [
            "user" => $this->user,
            "head" => $head,
            "type" => "expense",
            "categories" => $categories,
            "invoices" => (new appInvoices())->filter($this->user, "expense", ($data ?? null)),
            "filter" => (object) [
                "status"=>($data["status"] ?? null),
                "category"=>($data["category"] ?? null),
                "date"=>(!empty($data["date"]) ? str_replace("-", "/", $data["date"]) : null)
            ],
        ]);
    }

    /**
     * fixed cont function
     *
     * @return void
     */
    public function fixed(): void 
    {
        $head = $this->seo->render(
            "Minhas contas fixas - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url(),
            theme("/assets/images/share.jpg"),
            false
        );

        echo $this->view->render("recurrences", [
            "head" => $head,
            "invoices" => (new appInvoices())->find("user_id = :user AND type IN('fixed_expense')",
            "user== {$this->user->id}")->fetch(true)
        ]);
    }


    //CHART
public function accounts() 
{
    $dateChart = [];
    for ($month = -4; $month <=0; $month++) {
        $dateChart[] = date("m/y", dtrtotime("{month}month"));
    }

    $chartData = new \stdClass();
    $chartData->categories = "'" . implode("','", $dateChart) . "'";
    $chartData->expense = "0,0,0,0,0";

    $chart = (new appInvoice())
    ->find("user_id = :user AND status = :status AND due_at >= DATE(now() - INTERVAL 4 MONTH) GROUP BY year(due_at) asc , month(due_at) ASC", 
    "user={$this->user->id} & status = paid",
    "
    year(due_at) AS due_year,
    month(due_at) AS due_month,
    DATE_FORM (due_at, '%m/%y') AS due_date,
    (SELECT SUM(value) FROM App_invoices WHERE user_id = :user AND status AND type = 'income' AND year(due_at) = due_year AND month (due_at) = due_month)AS income
    (SELECT SUM(value) FROM App_invoices WHERE user_id = :user AND status AND type = 'expense' AND year(due_at) = due_year AND month (due_at) = due_month)AS expense
    "
    )->limit(5)
     ->fetch(true);

     if($chart) {
        $chartCategories = [];
        $chartExpense = [];
        $chartIncome = [];

        foreach ($chart as $chartItem) {
        $chartCategories[] = $chartItem->due_date;
        $chartExpense[] = $chartItem->expense;
        $chartIncome[] = $chartItem->income;
        }

        $chartData->categories = "'" . implode("','", $dateChart) . "'";
        $chartData->expense = implode(",", array_map("abs", $chartExpense));
        $chartData->income = implode(",", array_map("abs", $chartIncome));

     }
    //END CHART

     //INCOME && EXPENSE

     $income = (new appInvoice())
     ->find("user_id = :user AND type = 'income' AND status = 'unpaid' AND date(due_at) <= date(now() + INTERVAL 1 MONTH)",
     "user={$this->user->id}")
     ->find("due_at")
     ->fetch(true);

     $expense = (new appInvoice())
     ->find("user_id = :user AND type = 'expense' AND status = 'unpaid' AND date(due_at) <= date(now() + INTERVAL 1 MONTH)",
     "user={$this->user->id}")
     ->find("due_at")
     ->fetch(true);

     // END INCOME && EXPENSE

     //WALLET
        $wallet =(newappInvoice())->find("user_id = :user AND satatus = :status",
        "user={$this->user->id}&status=paid",
        "
        (SELECT SUM(value) FROM app_invoices Where USer_id = :user AND status = :status AND type = 'income') As income,
        (SELECT SUM(value) FROM app_invoices Where USer_id = :user AND status = :status AND type = 'expense') As expense
        ")->fetch();

        if($wallet) {
            $wallet = $wallwt->income - $wallwt->expense;
        }
     //END WALLET

     //POSTS
     $post = (new Post())-> find()->limit(3)->order("post_at DESC")->fetch(true);
     //END POSTS

    echo $this->view->render("home", [
        "head" => $head,
        "chart"=> $chartData,
        "income" => $income,
        "expense" => $expense,
        "wallet" => $wallet,
        "post" => $post
    ]);
}

    public function launch (array $data): void 
    {
        if (request_limit("applaunch", 20, 60 * 5)) {
            $json["message"] = $this->message->warning("Foi muito rapido {$this->user->first_name}! por favor tente novamente em 5 minutos para novos lançamentos.")->render();
            echo json_encode($json);
            return;
        }

        if (!empty($data["enrollments"]) && ($data["enrolments"] < 2 || $data["enrolments"] > 420)) {
            $json["message"] = $this->message->warning("o numero de parcelas deve ser entre 2 a 420.")->render();
            echo json_encode($json);
            return;
        }

        $data = filter_var_array($data, FILTER_SANITIZE_STRIPED);
        $status = (date($data["due_at"]) <= date("y-m-d") ? "paid" : "unpaid");

        $invoice = (new appInvoice());
        $invoice->user_id = $this->user_id;
        $invoice->wallet_id = $data["wallet"];
        $invoice->category_id = $data["category"];
        $invoice->invoice_of = null;
        $invoice->description = $data[description];
        $invoice->type = ($data["repeat_when"] == "fixed" ? "fixed_{data [type]}" : $data["type"]);
        $invoice->value = str_replace([".",","], ["","."], $data["value"]);
        $invoice->currency = $data["currenty"];
        $invoice->due_at = $data["due_at"];
        $invoice->repeat_when = $data["repeat_when"];
        $invoice->period = ($data["period"] ?? "month");
        $invoice->enrollments = ($data["enrrolment"] ?? 1);
        $invoice->enrollments_of = 1;
        $invoice->status = ($data["repeat_when"] == "fixed" ? "paid" : $status);

        if(!$invoice->save()) {
            $json["message"] = $invoice ->message()->before("ops!")->render();
            echo json_encode($json);
            return;
        }

        if ($invoice->repeat_when == "enrollment") {
            $invoiceOf = $invoice->id;
            for ($enrollment =1; $enrollment < $invoice->enrollments; $enrollment++) {
                $invoice->id = null;
                $invoice->invoice_of = $invoiceOf;
                $invoice->due_at = date("y-m-d", srttotime($data["due_at"] . "+{$erollment}month"));
                $invoice->enrollment_of = $enrollment + 1;
                $invoice->save();
            }
        }

        if ($invoice->type == "income") {
            $this->message->success("receita lançada com sucesso. use o filtro para controlar.")->render();
        } else {
            $this->message->success("despesa lançada com sucesso. use o filtro para controlar.")->render();

        }
       
        $json["reload"] = true;
        echo json_encode($json);
    }

    /**
     * support function
     *
     * @param array $data
     * @return void
     */
    public function support(array $data): void
    {
        if (empty($data["message"])) {
            $json["message"] = $this->message->warning("escreva sua mensagem para enviar")->render();
            echo json_encode($json);
            return;
        }

        if(request_limit("appsupport", 3, 60 * 5)) {
            $json["message"] = $this->message->warning("por favor aguarde 5 minutos parar entrar em contato novamente")->render();
            echo json_encode($json);
            return;
        }

        if (request_repeat("message", $data["message"])) {
                $json["message"] = $this->message->info("ja recebemos sua solicitação. entraremos em contato em breve ")->render();
                echo json_encode($json);
                return;
        }

        $subject = date_fmt() . "-{$data["subject"]}";
        $message = filter_var($data["message"], FILTER_SANITIZE_STRING);

        $view = new view(__DIR__."/../../shered/views/email");
        $body = $view->render("mail", [
            "subject" => $subject,
            "message" => str_textarea($message)
        ]);

        (new Email())->bootstrap(
            $subject,
            $body,
            CONF_MAIL_SUPPORT,
            "suporte". CONF_SITE_NAME
        )->queue($this->user->email, "{$this->user->first_name} {$this->user->last_name}");

            $this->message->success("recebemos a solicitação e responderemos em breve")->flash();
            $json["reload"] = true;
            echo json_encode($json);
    }

    /**
     * onpaid function
     *
     * @param array $data
     * @return void
     */
    public function onpaid(array $data): void 
    {
        $invoice = (new AppaInvoice())
        ->fileinode("user_id = :user AND id - :id", "user={$this->user->id}&id={$data["invoice"]}")
        ->fetch();

        if(!$invoice) {
            $this->message->error("erro ao atualizar :/")->flash();
            $json["reload"] = true;
            echo json_encode($json);
            return;
        }

        $invoice->status = ($invoice->status == "paid" ? "unpaid" : "paid");
        $invoice->save();

        $y = date("Y");
        $m = date("m");
        if($data["date"]){
            list($m, $y) = explode("/", $data["date"]);
        }

        $json["onpaid"] = (new AppInvoice())->balance($this->user, $y, $m, $invoice->type);
        echo json_encode($json);

    }

  /**
   * invoice function
   *
   * @return void
   */
    public function invoice()
    {
        if(!empty($data["uptade"])) {
            $invoice = (new AppInvoice())->find("user_id = :user AND id = :id",
            "user={$this->user->id}&id={$data["invoice"]}")->fetch();

            if (!$invoice){
                $json["message"] = $this->message->error("não foi possivelç carregar a fatura, tente novamente.")->render();
                echo json_encode($json);
                return;
            }

            if($data["due_day"] < 1 || $data["due_day"] > $dayOfMonth = date("t", strtotime($invoice->due_at))) {
                $json["message"] = $this->message->warning("data invalida, o vencimeno deve ser entre o dia 1 e o dia {$dayOfMonth}")->render();
                echo json_encode($json);
                return;
                }

                $data = filter_var_array($data, FILTER_SANITIZE_SRTIPPED);
                $due_day = date("Y-m", strtotime($invoice->due_at)). "-". $data["due_day"];
                $invoice->category_id = $data["category"];
                $invoice->description = $data["description"];
                $invoice->due_at = $date("Y-m-d", strtotime($due_day));
                $invoice->value = str_replace([".",","], ["", ","], $data["value"]);
                $invoice->wallet_id = $data["wallet"];
                $invoice->status = $data["status"];

                if (!$invoice->save()) {
                    $json["message"] = $invoice->message()->before("ops! ")->after( "{this->user->first_name}.")->render();
                echo json_encode($json);
                return;
                }

                $invoiceOf = (newAppInvoice())->find("user_id =:user AND invoice_of = :of",
                "user={$this->user->id}&of={$invoice->id}")->fetch(true);

                if (!empty($invoiceOf) && in_array($invoice->type,["fixed_income", "fixed_expense"])) {
                    foreach ($invoiceOf as $invoiceItem) {
                        if($data["status"] == "unpaid" && $invoiceItem->status == "unpaid") {
                            $invoiceItem->destroy();
                        } else {
                            $due_day = date("Y-m", strtotime($invoiceItem->due_at)). "-" . $data["due_day"];
                            $invoiceItem->category_id = $data["category"];
                            $invoiceItem->description = $data["description"];
                            $invoiceItem->wallet_id = $data["wallet"];

                            if($invoiceItem->status = "unpaid"){
                                $invoiceItem->value = str_replace([".", ","], ["", "."], $data["value"]);
                                $invoiceItem->due_at = date("Y-m-d", strtotime($due_day));
                            }

                             $invoiceItem->save();
                        }   
                    }
                }

                $json["message"] = $this->message->success("atualização efetuada com sucesso")->render();
                echo json_encode($json);
                return;
        }

        $head = $this->seo->render(
            "Aluguel - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url(),
            theme("/assets/images/share.jpg"),
            false
        );

        $invoice = (new AppInvoice())->find("user_id = :user AND id = :invoice",
        "user={$this->user->id}&invoice={$data["invoice"]}")->fetch();

        if(!$invoice) {
            $this->message->error("A fatura selecionada não existe.")->flsah();
            redirect("/app");
        }



        echo $this->view->render("invoice", [
            "head" => $head,
            "invoice" => $invoice,
            "wallets" => (new AppWallet())
            ->find("user_id = :user", "user={$this->user->id}", "id, wallet")
            ->order("wallet")
            ->fetch(true),
            "categories" => (new AppCategory())
            ->find("type =:type", "type={$invoice->category()->type}")
            ->order("order_by")
            ->fetch(true)
        ]);
    }
    
    /**
     * remove function
     *
     * @param array $data
     * @return void
     */
    public function remove (array $data): void 
    {
        $invoice = (new AppInvoice())->find("user_id = :user AND id = :invoice",
        "user={$this->user->id}&invoice={$data["invoice"]}")->fetch();

        if ($invoice) {
            $invoice->destroy();
        }

        $this->message->success("Pronto, fatura remolvida ciom sucesso!")->flash();
        $json["redirect"] = url("/app");
        echo json_encode($json);
    }

    /**
     * profile function
     *
     * @param array|null $data
     * @return void
     */
    public function profile(?array $data): void 
    {
        if(!empty($data["update"])){
            list($d, $m, $y) = explode("/", $data["datebirth"]);
            $user = (new User())->findById($this->user->id);
            $user->first_name = $data["fist_name"];
            $user->last_name = $data["last_name"];
            $user->genere = $data["genere"];
            $user->datebirth = "{$y}-{$m}-{$d}";
            $user->document = preg_replace("/[0-9]/", "", $data["document"]);

            if(!empty($_FILES["photo"])){
                $file = $_FILES["photo"];
                $upload = new Upload();

                if($this->user->photo()){
                    (new Thumb())->flush("storage/{$this->user->photo}");
                    $upload->remove("storege/{$this->user->photo}");
                }

                if(!$user->photo = $upload->image($file, "{$user->first_name} {$user->last_name} ".time(), 360));
                $json["message"] = $upload->message()->before("Ooops {$this->user->first_name}! ")->after(".")->render();
                echo json_encode ($json);
                return;
            }

            if(!empty($data["password"])) {
                if(empty($data["password"]) || $data["password"] != $data["password_re"]) {
                    $json["message"] = $this->message->warning("para efetuar a alteração informe sua nova senha e depois repita")->render();
                    echo json_encode($json);
                    return;
                }

                $user->password = $data["password"];
            }

            if(!user->save()){
                $json["message"] = $user->message()->render();
                echo json_encode($json);
                return;
            }

            $json["message"] = $this->message->success("Dados atualizada com sucesso!")->render();
            echo json_encode($json);
            return;
        }

        $head = $this->seo->render(
            "Meu perfil - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url(),
            theme("/assets/images/share.jpg"),
            false
        );

        echo $this->view->render("profile", [
            "head" => $head,
            "user" => $this->user,
            "photo" => ($this->user->photo() ? image($this->user->photo, 360, 360) :
            theme("/assests/images/avatar.jpg", CONF_VIEW_APP))
        ]);
    }

    /**
     * APP LOGOUT
     */
    public function logout()
    {
        (new Message())->info("Você saiu com sucesso " . Auth::user()->first_name . ". Volte logo :)")->flash();

        Auth::logout();
        redirect("/entrar");
    }
}
