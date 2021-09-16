<?php
require_once('api/Simpla.php');

class Roskassa extends Simpla
{	
	public function checkout_form($order_id, $button_text = null)
	{
		if(empty($button_text))
		{
			$button_text = 'Оплатить';
		}
		
		$order = $this->orders->get_order((int)$order_id);
		$payment_method = $this->payment->get_payment_method($order->payment_method_id);
		$payment_currency = $this->money->get_currency(intval($payment_method->currency_id));
		$settings = $this->payment->get_payment_settings($payment_method->id);	

		$purchases = $this->orders->get_purchases(array('order_id'=>intval($order->id)));
		$delivery = $this->delivery->get_delivery($order->delivery_id);

		$success_url = $this->config->root_url.'/order/'.$order->url;
		$fail_url = $this->config->root_url.'/order/'.$order->url;

		$m_url = $settings['roskassa_merchanturl'];
		$m_shop = $settings['roskassa_merchantid'];
		$m_orderid = $order->id;
		$m_amount = number_format($order->total_price, 2, '.', '');
		$m_curr = $payment_currency->code == 'RUR' ? 'RUB' : $payment_currency->code;

		$sign_arr = array(
			'shop_id' => $m_shop,
			'order_id' => $m_orderid,
			'amount' => $m_amount,
			'currency' => $m_curr,
		);

		if ($settings['roskassa_test_mode'] == 1) {
			$sign_arr['test'] = 1;
		}

		ksort($sign_arr);
		$str = http_build_query($sign_arr);
		$sign = md5($str . $settings['roskassa_secret']);

		$data = '';
		$i = 0;

		foreach ($purchases as $item) {
			$data .= '<input type="hidden" name="receipt[items]['.$i.'][name]" value="'.$item->product_name.'">';
			$data .= '<input type="hidden" name="receipt[items]['.$i.'][count]" value="'.$item->amount.'">';
			$data .= '<input type="hidden" name="receipt[items]['.$i.'][price]" value="'.$item->price.'">';

			$i++;
		} 

		if ($order->delivery_price > 0) {
			$data .= '<input type="hidden" name="receipt[items]['.$i.'][name]" value="Доставка">';
			$data .= '<input type="hidden" name="receipt[items]['.$i.'][count]" value="1">';
			$data .= '<input type="hidden" name="receipt[items]['.$i.'][price]" value="'.$order->delivery_price.'">';
		}
		
		$button = '
		<form method="GET" action="' . $m_url . '">
		<input type="hidden" name="shop_id" value="' . $m_shop . '">
		'.$data.'
		<input type="hidden" name="amount" value="' . $m_amount . '">
		<input type="hidden" name="order_id" value="' . $m_orderid . '">';
		
		if ($settings['roskassa_test_mode'] == 1) {
			$button .= '<input type="hidden" name="test" value="1">';
		}
		$button .= '
		<input type="hidden" name="success_url" value="' . $success_url . '">
		<input type="hidden" name="fail_url" value="' . $fail_url . '">
		<input type="hidden" name="sign" value="' . $sign . '">
		<input type="submit" name="m_process" value="' . $button_text . '" />
		</form>';

		return $button;
	}
}
?>