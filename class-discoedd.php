<?php
/**
 * File for class wrote for add/update/remove users to Discourse Groups
 *
 * @package DiscourseEDD
 */

namespace DiscoEdd;

/**
 * Class for code to process add/update/remove users to Discourse Groups
 */
class DiscoEdd {


	public function __construct() {

		// Edd software Licensing core, after purchasing a product/download.
		add_action( 'edd_complete_download_purchase', array( $this, 'edd_complete_download_purchase' ), 100, 5 );

		// After license generation.
		add_action( 'edd_sl_store_license', array( $this, 'edd_on_license_generate' ), 100, 4 );

		// Revokes license keys on payment status change (if needed).
		add_action( 'edd_sl_pre_revoke_license', array( $this, 'edd_pre_revoke_license' ), 100, 2 );

		// Delete license keys on payment deletion.
		add_action( 'edd_sl_pre_delete_license', array( $this, 'edd_pre_delete_license' ), 100, 2 );

		// Renews a license on purchase.
		add_action( 'edd_sl_post_license_renewal', array( $this, 'edd_post_license_renewal' ), 100, 2 );

		// On new user registration add the user to groups in case of any previous guest purchase.
		add_action( 'user_register', array( $this, 'add_past_license_keys_to_new_user' ), 100 );

		// Before deleting a subscription add/remove user from a group.
		add_action( 'edd_recurring_before_delete_subscription', array( $this, 'edd_recurring_before_delete_subscription' ), 100 );

		// On subscription renewal add/remove user from a group.
		add_action( 'edd_subscription_post_renew', array( $this, 'edd_subscription_post_renew' ), 100, 4 );

		// On subscription expiration add/remove user from a group.
		add_action( 'edd_subscription_expired', array( $this, 'edd_subscription_expired' ), 100, 2 );

		// On subcription cancellation add/remove user from a group.
		add_action( 'edd_subscription_cancelled', array( $this, 'edd_subscription_cancelled' ), 100, 2 );

	}

	public function edd_complete_download_purchase( $download_id, $payment_id, $download_type, $download, $cart_index ) {
		// Get user id(s) from payment meta and add/remove user from the group, if it is a guest payment then do nothing.
		// Get group id(s) from download meta and add/remove user from the group.
		$this->add_remove_user_using_download_payment( $download_id, $payment_id );
	}

	public function edd_on_license_generate( $license_id, $purchased_download_id, $payment_id, $license_type ) {
		// Get user id(s) from payment meta and add/remove user from the group, if it is a guest payment then do nothing.
		// Get group id(s) from download meta and add/remove user from the group.
		$this->add_remove_user_using_download_payment( $purchased_download_id, $payment_id );
	}

	public function edd_pre_revoke_license( $license_id, $payment_id ) {
		// Get user id(s) from payment meta and add/remove user from the group, if it is a guest payment then do nothing.
		// Get group id(s) from download meta and add/remove user from the group and to get download use license id.
		$this->add_remove_user_using_license( $license_id );
	}

	public function edd_pre_delete_license( $license_id, $payment_id ) {
		// Get user id(s) from payment meta and add/remove user from the group, if it is a guest payment then do nothing.
		// Get group id(s) from download meta and add/remove user from the group and to get download use license id.
		$this->add_remove_user_using_license( $license_id );
	}

	public function edd_post_license_renewal( $license_id, $new_expiration ) {
		// Get user id(s) from license meta and add/remove user from the group, if it is a guest payment then do nothing.
		// Get group id(s) from download meta and add/remove user from the group and to get download use license id.
		$this->add_remove_user_using_license( $license_id );
	}

	public function edd_recurring_before_delete_subscription( $edd_subscription ) {
		// Get all downloads related with the subscription, loop on the downloads.
		// Get group id(s) from download meta and add/remove user from the group.
		$this->add_remove_user_using_subscription_id( $edd_subscription->id );
	}

	public function edd_subscription_post_renew( $edd_subscription_id, $expiration, $edd_subscription, $payment_id ) {
		// Get all downloads related with the subscription, loop on the downloads.
		// Get group id(s) from download meta and add/remove user from the group.
		$this->add_remove_user_using_subscription_id( $edd_subscription_id );
	}

	public function edd_subscription_expired( $edd_subscription_id, $edd_subscription ) {
		// Get all downloads related with the subscription, loop on the downloads.
		// Get group id(s) from download meta and add/remove user from the group.
		$this->add_remove_user_using_subscription_id( $edd_subscription_id );
	}

	public function edd_subscription_cancelled( $edd_subscription_id, $edd_subscription ) {
		// Get all downloads related with the subscription, loop on the downloads.
		// Get group id(s) from download meta and add/remove user from the group.
		$this->add_remove_user_using_subscription_id( $edd_subscription_id );
	}

	public function add_remove_user_using_subscription_id( $edd_subscription_id ) {
		$edd_subscription = new EDD_Subscription( $edd_subscription_id );
		if ( ! empty( $edd_subscription ) ) {
			$payment_id = $edd_subscription->get_original_payment_id();
			if ( ! empty( $payment_id ) ) {
				$payment = new EDD_Payment( $payment_id );
				if ( $payment->downloads ) {
					foreach ( $payment->downloads as $download ) {
						$this->add_remove_user_using_download_payment( (int) $download['id'], $payment_id );
					}
				}
				unset( $payment );
			}
		}
		unset( $edd_subscription );
	}

	public function add_remove_user_using_download_payment( $download_id, $payment_id ) {
		$payment = new EDD_Payment( $payment_id );
		if ( ! empty( $payment->user_id ) ) {
			$this->assign_discourse_groups( $download_id, $payment->user_id );
		}
		unset( $payment );
	}

	public function add_remove_user_using_license( $license_id ) {
		$license = new EDD_Software_Licensing( $license_id );
		if ( ! empty( $license ) && ! empty( $license->user_id ) && ! empty( $license->download_id ) ) {
			$this->assign_discourse_groups( $license->download_id, $license->user_id );
		}
		unset( $license );
	}

	public function assign_discourse_groups( $download_id, $user_id ) {
		$discourse_groups_meta = get_post_meta( $license->download_id, 'discourse_groups' );
		foreach ( $discourse_groups_meta as $discourse_group_setting ) {
			if ( ! empty( $discourse_group_setting->action ) && ! empty( $discourse_group_setting->group ) ) {
				$this->add_remove_user( $user_id, $discourse_group_setting->group, $discourse_group_setting->action );
			}
		}
	}

	public function add_remove_user( $user_id, $group, $action = 'add' ) {
		// Will wp discourse plugin add/remove user to or from a group.
	}

}
new DiscoEdd();
