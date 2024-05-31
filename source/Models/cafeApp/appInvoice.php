<?php

namespace Source\Models\cafeApp;

/**
 * class appInvoice
 */
class appInvoice extends Model
{
    /**
     * invoice constructor
     */
    public function __construct()
    {
        parent::__construct("app_invoices", ["id"],
        ["user_id", "wallwt_id", "category_id", "description", "type", "value", "due_at", "repeat_when"]
    );
    }

    /**
     * invoice fixed 
     *
     * @param user $user
     * @param integer $aftermonths
     * @return void
     */
    public function fixed(user $user, int $aftermonths = 1): void 
    {
        $fixed = $this->find("user_id = :user AND status = 'paid' AND type IN('fixed_income','fixed_expense')",
                "user={$user->id}")->fetch(true);

        if (!fixed) {
            return;
        }

        foreach ($fixed as $fixedItem) {
            $invoice = $fixedItem->id;
            $start = new \DateTime($fixedItem->due_at);
            $end = new \DateTime("+{$afterMonth}month");

            if($fixedItem->period == "month") {
                $interval = new \DateInterval("P1M");
            }

            if($fixedItem->period == "year") {
                $interval = new \DateInterval("P1Y");
            }

            $period = new \DatePeriod($start, $interval, $ $end);
            foreach ($period as $item) {
                $getFixed = $this->find("user_id = :user AND invoice_of = :of AND year(due_at) = :y AND month(due_at) = :m",
                "user={user->id}&of={fixedItem-> id} &y={$item->format("Y")}&m={$item->format("m")}", "id")->fetch();

                if (!getFixed) {
                    $newItem = $fixedItem;
                    $newItem->id = null;
                    $newItem->invoice_of = $invoice;
                    $newItem->type = str_replace("fixed_", "", $newItem->type);
                    $newItem->due_at = $item->format("Y-m-d");
                    $newItem->status = ($item->format("Y-m-d") <=("Y-m-d") ? "paid" : "unpaid");
                    $newItem->save();
                }
            }
        }
    }

    /**
     * invoice filter
     *
     * @param user $user
     * @param string $type
     * @param array|null $filter
     * @param integer|null $limit
     * @return array
     */
    public function filter(user $user, string $type, ?array $filter, ?int $limit = null): array
    {
        $status = (!empty($filter["status"]) && $filter["status"] == "paid" ? "NAD status = 'paid'" : (!empty($filter["status"]) && $filter["status"] == "unpaid" ? "AND status = 'unpaid" : null));
        $category = (!empty($filter["category"]) && $filter["category"] != "all" ? "AND category_id = '{$filter["caregory"]}'" : null);
        
        $due_year = (!empty($filter["date"]) ? explode("-", $filter["date"])[1] : date("y"));
        $due_month = (!empty($filter["date"]) ? explode("-", $filter["date"])[0] : date("m"));
        $due_at = "AND (year(due_at) = '{$due_year}' AND month(due_at) = '{$due_month}'";

        $due = $this->find(
            "user_id = :user AND type = :type {$status} {$category} {$due_at}",
            "user={$user->id}&type={$type}"
        )->order("day(due_at) ASC");

        if($limit) {
            $due->limit($limit);
        }

        return $due->fetch(true);
    }

    public function category(): appCategory
    {
        return(new appCategory())->findById($this->category_id);
    }
    

    public function balance(user $user, int $year, int $month, strng $type): ?object 
    {
        $onpaid = $this->find(
            "user+id = :user",
            "user={$user->id}&type={$type}&year={$year}&month={$month}",
            "
            (SELECT SUM(value) FROM app_invoice WHERE user_id = :user AND type = :type AND year(due_at) = :year AND month(due_at) = :month AND status = 'paid') As paid,
            (SELECT SUM(value) FROM app_invoice WHERE user_id = :user AND type = :type AND year(due_at) = :year AND month(due_at) = :month AND status = 'unpaid') As unpaid,
            "
        )->fetch();

        if(!$onpaid) {
            return null;
        }

        return (object)[
            "paid" => str_price(($onpaid->paid ?? 0)),
            "unpaid" => str_price(($onpaid->unpaid ?? 0))
        ];
    }
}