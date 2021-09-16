<?php
chdir ('../../');
require_once('api/Simpla.php');

if (isset($_REQUEST["id"]) && isset($_REQUEST["sign"]))
{
	// загрузка заказа
	
	$simpla = new Simpla();
	$order_id = $_REQUEST['order_id'];
	$order = $simpla->orders->get_order(intval($order_id));
	
	if(!empty($order))
	{
		$method = $simpla->payment->get_payment_method(intval($order->payment_method_id));
		
		if(!empty($method))
		{
			$err = false;
			$message = '';
			$settings = unserialize($method->settings);
			
			// запись логов

            $log_text =
                "--------------------------------------------------------\n" .
                "id you	shoop   	" . $_REQUEST["shop_id"] . "\n" .
                "amount				" . $_REQUEST["amount"] . "\n" .
                "kassa operation id " . $_REQUEST["id"] . "\n" .
                "mercant order id	" . $_REQUEST["order_id"] . "\n" .
                "currency			" . $_REQUEST["currency"] . "\n" .
                "sign				" . $_REQUEST["sign"] . "\n\n";
			
			$log_file = $settings['roskassa_log'];
			
			if (!empty($log_file))
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
			
			$data = $_POST;
			unset($data['sign']);
			ksort($data);
			$str = http_build_query($data);
			$sign_hash = md5($str . $settings['roskassa_secret']);
			
			if (!$err)
			{
				// проверка суммы
				
				if ($_REQUEST['amount'] != $order->total_price)
				{
					$message .= " - неправильная сумма\n";
					$err = true;
				}
				
				// проверка статуса
				
				if (!$err)
				{
					if ($_REQUEST['sign'] == $sign_hash) {

                        if (!$order->paid) {
                            $simpla->orders->update_order(intval($order_id),
                                array(
                                    'paid' => 1,
                                    'status' => $settings['roskassa_order_status']
                                ));

                            $simpla->notify->email_order_user(intval($order_id));
                            $simpla->orders->close(intval($order_id));
                        }

                    }
                    else {

                        $message .= " - не совпадают цифровые подписи\n";
                        $err = true;
                    }
				}
			}
			
			if ($err)
			{
				$to = $settings['roskassa_email'];

				if (!empty($to))
				{
					$message = "Не удалось провести платёж через систему RosKassa по следующим причинам:\n\n" . $message . "\n" . $log_text;
					$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
					"Content-type: text/plain; charset=utf-8 \r\n";
					mail($to, 'Ошибка оплаты', $message, $headers);
				}
				
				exit($order_id . ' | error | ' . $message);
			}
			else
			{
				exit('YES');
			}
		}
	}
}