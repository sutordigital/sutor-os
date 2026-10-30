<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sutor_Lead_OS_Leads_Page {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
	}

	public static function menu() {
		add_submenu_page(
			'sutor-lead-os',
			'Leads',
			'Leads',
			'manage_options',
			'sutor-lead-os-leads',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$page = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$per  = 50;
		$total = Sutor_Lead_OS_DB::count_leads();
		$leads = Sutor_Lead_OS_DB::list_leads( $per, ( $page - 1 ) * $per );
		?>
		<div class="wrap">
			<h1>Lead OS — Leads</h1>
			<p><?php echo esc_html( $total ); ?> total leads stored in the WordPress database.</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Created (UTC)</th>
						<th>Lead ID</th>
						<th>Name</th>
						<th>Phone</th>
						<th>Qualified</th>
						<th>Reason</th>
						<th>Attribution</th>
						<th>Answers</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $leads ) ) : ?>
					<tr><td colspan="8">No leads yet.</td></tr>
				<?php else : foreach ( $leads as $lead ) : ?>
					<tr>
						<td><?php echo esc_html( $lead->created_at ); ?></td>
						<td><code><?php echo esc_html( $lead->lead_id ); ?></code></td>
						<td><?php echo esc_html( $lead->contact_name ); ?></td>
						<td><?php echo esc_html( $lead->contact_phone ); ?></td>
						<td><?php echo (int) $lead->qualified ? 'Yes' : 'No'; ?></td>
						<td><?php echo esc_html( $lead->qualify_reason ); ?></td>
						<td>
							<?php
							$bits = array_filter( array(
								$lead->gclid ? 'gclid' : '',
								$lead->utm_source ? 'utm:' . $lead->utm_source : '',
							) );
							echo esc_html( implode( ', ', $bits ) );
							?>
							<div class="description"><?php echo esc_html( $lead->landing_page ); ?></div>
						</td>
						<td><code style="white-space:pre-wrap;font-size:11px;"><?php echo esc_html( $lead->answers ); ?></code></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
