<?php
/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Template for displaying the unsubscribe link request form
 * Used when old MD5 unsubscribe links are detected or when tokens are invalid
 * 
 * @var string $popup Popup ID
 * @var string $status Status message (success, error, not_found)
 * @var string $homeUrl Home URL
 * @var string $actionUrl Admin post action URL
 * @var string $emailValue Pre-filled email value if available
 */
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e('Request New Unsubscribe Link', 'popup-builder'); ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: #f0f0f1;
			margin: 0;
			padding: 20px;
			display: flex;
			justify-content: center;
			align-items: center;
			min-height: 100vh;
		}
		.unsubscribe-form-container {
			background: #fff;
			padding: 40px;
			border-radius: 8px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
			max-width: 500px;
			width: 100%;
		}
		.unsubscribe-form-container h2 {
			margin-top: 0;
			color: #1d2327;
			font-size: 24px;
		}
		.unsubscribe-form-container p {
			color: #646970;
			margin-bottom: 20px;
			line-height: 1.6;
		}
		.unsubscribe-form-container .form-group {
			margin-bottom: 20px;
		}
		.unsubscribe-form-container label {
			display: block;
			margin-bottom: 8px;
			color: #1d2327;
			font-weight: 600;
		}
		.unsubscribe-form-container input[type="email"] {
			width: 100%;
			padding: 12px;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			font-size: 16px;
			box-sizing: border-box;
		}
		.unsubscribe-form-container input[type="email"]:focus {
			border-color: #2271b1;
			outline: none;
			box-shadow: 0 0 0 1px #2271b1;
		}
		.unsubscribe-form-container button {
			background: #2271b1;
			color: #fff;
			border: none;
			padding: 12px 24px;
			border-radius: 4px;
			font-size: 16px;
			cursor: pointer;
			width: 100%;
			font-weight: 600;
		}
		.unsubscribe-form-container button:hover {
			background: #135e96;
		}
		.unsubscribe-form-container .message {
			padding: 12px;
			border-radius: 4px;
			margin-bottom: 20px;
		}
		.unsubscribe-form-container .message.success {
			background: #00a32a;
			color: #fff;
		}
		.unsubscribe-form-container .message.error {
			background: #d63638;
			color: #fff;
		}
	</style>
</head>
<body>
	<div class="unsubscribe-form-container">
		<h2><?php esc_html_e('Your link has expired', 'popup-builder'); ?></h2>
		<p><?php esc_html_e('Enter your email to receive a new unsubscribe link.', 'popup-builder'); ?></p>
		
		<?php if (!empty($status)) : ?>
			<?php if ($status === 'success') : ?>
				<div class="message success">
					<?php esc_html_e('A new unsubscribe link has been sent to your email address.', 'popup-builder'); ?>
				</div>
			<?php elseif ($status === 'error') : ?>
				<div class="message error">
					<?php esc_html_e('An error occurred. Please try again or contact the administrator.', 'popup-builder'); ?>
				</div>
			<?php elseif ($status === 'not_found') : ?>
				<div class="message error">
					<?php esc_html_e('Email address not found in our subscription list.', 'popup-builder'); ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		
		<?php if ($status !== 'success') : ?>
			<form method="post" action="<?php echo esc_url($actionUrl); ?>">
				<input type="hidden" name="action" value="sgpb_request_new_unsubscribe_link">
				<input type="hidden" name="popup" value="<?php echo esc_attr($popup); ?>">
				<?php wp_nonce_field('sgpb_request_unsubscribe_link', 'sgpb_unsubscribe_nonce'); ?>
				<div class="form-group">
					<label for="sgpb_unsubscribe_email"><?php esc_html_e('Email Address', 'popup-builder'); ?></label>
					<input type="email" id="sgpb_unsubscribe_email" name="email" required placeholder="<?php esc_attr_e('your@email.com', 'popup-builder'); ?>" value="<?php echo esc_attr($emailValue); ?>">
				</div>
				<button type="submit"><?php esc_html_e('Send New Unsubscribe Link', 'popup-builder'); ?></button>
			</form>
		<?php endif; ?>
		
		<p style="margin-top: 20px; font-size: 14px;">
			<a href="<?php echo esc_url($homeUrl); ?>"><?php esc_html_e('Return to homepage', 'popup-builder'); ?></a>
		</p>
	</div>
</body>
</html>

