<?php
if($lang == "fi") $loc = "fi-FI"; else $loc = "en-US";

$result = $db->query("SELECT * FROM tuspe_cart_products WHERE cart_id = '{$cart_data["id"]}'");

if($result->num_rows > 0){

	if($cart_data["coupon"]["percent"]) $coupon = $cart_data["coupon"]["percent"]; else $coupon = 0;
    $products = array();
    $order_total = $cart_data["checkout"]["shipping_total"] * 100;
    $order_vat = $cart_data["checkout"]["shipping_vat"] * 100;
    $name = explode(" ", $cart_data["customer"]["name"]);
    if(!$cnf) $cnf = $pages->get("template=config");

    $passu = "Authorization: Basic ". base64_encode("shop_merchant_id:shop_merchant_secret");
    $array = array($passu, 'Content-Type: application/json');

	// Create array of products
    while($row = $result->fetch_assoc()){

        $total = $row["price"] * $row["quantity"];
        if($coupon > 0){
            $discount = number_format($coupon * $total / 100, 2, '.', '');
            $total = $total - $discount;
            $discount *= 100;
        } else $discount = 0;
        $products[] = array(
            "type" => "physical",
            "reference" => $row["sku"],
            "name" => $row["title"],
            "quantity" => $row["quantity"],
            "quantity_unit" => "pcs",
            "unit_price" => $row["price"] * 100,
            "tax_rate" => $row["vat"] * 100,
            "total_amount" => number_format($total, 2, '.', '') * 100,
            "total_discount_amount" => $discount,
            "total_tax_amount" => number_format($total - ($total / 1.24), 2, '.', '') * 100
        );

    }

	// Shipping as normal product
    $products[] = array(
        "type" => "physical",
        "reference" => $cart_data["shipping"]["id"],
        "name" => $cart_data["shipping"]["title"],
        "quantity" => 1,
        "quantity_unit" => "pcs",
        "unit_price" => $cart_data["shipping"]["price"] * 100,
        "tax_rate" => 2400,
        "total_amount" => $cart_data["shipping"]["price"] * 100,
        "total_tax_amount" => number_format($cart_data["shipping"]["price"] - $cart_data["shipping"]["price"] / 1.24, 2, '.', '') * 100
    );

	// Order content
    $orders = array(
        "purchase_country" => "fi",
        "purchase_currency" => "eur",
        "locale" => "$loc",
        "billing_address" => array(
            "given_name" => $name[0],
            "family_name" => $name[1],
            "email" => $cart_data["customer"]["email"],
            "street_address" => $cart_data["customer"]["street"],
            "postal_code" => $cart_data["customer"]["postcode"],
            "city" => $cart_data["customer"]["area"],
            "phone" => $cart_data["customer"]["phone"],
            "country" => "fi"
        ),
        "shipping_address" => array(
            "given_name" => $name[0],
            "family_name" => $name[1],
            "email" => $cart_data["customer"]["email"],
            "street_address" => $cart_data["customer"]["street"],
            "postal_code" => $cart_data["customer"]["postcode"],
            "city" => $cart_data["customer"]["area"],
            "phone" => $cart_data["customer"]["phone"],
            "country" => "fi"
        ),
        "order_amount" => $order_total,
        "order_tax_amount" => $order_vat,
        "order_lines" => $products,
        "merchant_urls" => [
            "terms" => $pages->get("name=toimitusehdot")->httpUrl,
            "checkout" => "{$page->httpUrl}/klarna?kid={checkout.order.id}",
            "confirmation" => "{$page->httpUrl}/2?kid={checkout.order.id}",
            "push" => "{$page->httpUrl}/klarna?kid={checkout.order.id}"
        ]
    );

    $orders = json_encode($orders);
    $ch = curl_init('https://api.klarna.com/checkout/v3/orders');
    curl_setopt_array($ch, array(
        CURLOPT_POST => TRUE,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => $array,
        CURLOPT_POSTFIELDS => $orders
    ));
    $result=curl_exec ($ch);
    curl_close ($ch);
    $result = json_decode($result, true);
    echo $result["html_snippet"];

}
