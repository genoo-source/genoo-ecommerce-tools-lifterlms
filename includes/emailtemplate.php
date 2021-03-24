<?php
 $emailbody = '
<html>
<head>
</head>
<body>
<table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: rgb(255, 255, 255); border: 1px solid rgb(222, 222, 222); box-shadow: rgba(0, 0, 0, 0.1) 0px 1px 4px; border-radius: 3px;">
							<tbody><tr>
								<td align="center" valign="top">
									
									<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: rgb(150, 88, 138); color: rgb(255, 255, 255); border-bottom: 0px; font-weight: bold; line-height: 100%; vertical-align: middle; font-family: &quot;Helvetica Neue&quot;, Helvetica, Roboto, Arial, sans-serif; border-radius: 3px 3px 0px 0px;">
										<tbody><tr>
											<td style="padding: 36px 48px; display: block;">
												<h1 style="font-family: &quot;Helvetica Neue&quot;, Helvetica, Roboto, Arial, sans-serif; font-size: 30px; font-weight: 300; line-height: 150%; margin: 0px; text-align: left; text-shadow: rgb(171, 121, 161) 0px 1px 0px; color: rgb(255, 255, 255); background-color: inherit;">Welcome to Membership Grow Revenues</h1>
											</td>
										</tr>
									</tbody></table>
									
								</td>
							</tr>
							<tr>
								<td align="center" valign="top">
									
									<table border="0" cellpadding="0" cellspacing="0" width="600">
										<tbody><tr>
											<td valign="top" style="background-color: rgb(255, 255, 255);">
												
												<table border="0" cellpadding="20" cellspacing="0" width="100%">
													<tbody><tr>
														<td valign="top" style="padding: 48px 48px 32px;">
															<div style="color: rgb(99, 99, 99); font-family: &quot;Helvetica Neue&quot;, Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left;">

<p style="margin: 0px 0px 16px;">Hi "'.$username.'",</p>
<p style="margin: 0px 0px 16px;">Thanks for creating an account on Member Grow Revenues. Your username is <strong>'.$username.'</strong> You can access your account area to view orders, change your password, and more at: <a href='.$url.' rel="nofollow" style="color: rgb(150, 88, 138); font-weight: normal; text-decoration: underline;">'.$url.'</a></p>
		<p style="margin: 0px 0px 16px;">Your password has been automatically generated: <strong>'.$password.'</strong></p>

<p style="margin: 0px 0px 16px;">We look forward to seeing you soon.</p>
															</div>
														</td>
													</tr>
												</tbody>
												</table>
</body>
</html>';

// Always set content-type when sending HTML email
$headers1 = "MIME-Version: 1.0" . "\r\n";
$headers1 .= "Content-type:text/html;charset=UTF-8" . "\r\n";
 wp_mail($email,'Your member Grow Revenues account has been created!',$emailbody,$headers1);
 $update_meta = update_user_meta($value,'store_users',$value);
 ?>