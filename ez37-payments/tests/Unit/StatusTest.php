<?php
/**
 * Tests for PaymentStatus and FulfilmentStatus state machines.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use EZ37\Payments\Transactions\FulfilmentStatus;
use EZ37\Payments\Transactions\PaymentStatus;

final class StatusTest extends TestCase {

	public function test_payment_happy_path(): void {
		$this->assertTrue( PaymentStatus::Pending->can_transition_to( PaymentStatus::Paid ) );
	}

	public function test_delayed_payment_path(): void {
		$this->assertTrue( PaymentStatus::Pending->can_transition_to( PaymentStatus::Processing ) );
		$this->assertTrue( PaymentStatus::Processing->can_transition_to( PaymentStatus::Paid ) );
		$this->assertTrue( PaymentStatus::Processing->can_transition_to( PaymentStatus::Failed ) );
	}

	public function test_paid_is_final_so_late_events_cannot_undo_it(): void {
		$this->assertTrue( PaymentStatus::Paid->is_final() );
		$this->assertFalse( PaymentStatus::Paid->can_transition_to( PaymentStatus::Expired ) );
		$this->assertFalse( PaymentStatus::Paid->can_transition_to( PaymentStatus::Failed ) );
		$this->assertFalse( PaymentStatus::Paid->can_transition_to( PaymentStatus::Pending ) );
	}

	public function test_no_payment_status_can_return_to_pending(): void {
		foreach ( PaymentStatus::cases() as $status ) {
			$this->assertFalse( $status->can_transition_to( PaymentStatus::Pending ), $status->value );
		}
	}

	public function test_no_self_transitions(): void {
		foreach ( PaymentStatus::cases() as $status ) {
			$this->assertFalse( $status->can_transition_to( $status ), $status->value );
		}
	}

	public function test_expired_cannot_become_paid(): void {
		$this->assertFalse( PaymentStatus::Expired->can_transition_to( PaymentStatus::Paid ) );
	}

	public function test_allowed_from_builds_conditional_update_set(): void {
		$this->assertSame(
			array( PaymentStatus::Pending, PaymentStatus::Processing ),
			PaymentStatus::Paid->allowed_from()
		);
		$this->assertSame( array( PaymentStatus::Pending ), PaymentStatus::Expired->allowed_from() );
		$this->assertSame( array(), PaymentStatus::Pending->allowed_from() );
	}

	public function test_stored_values_match_schema_defaults(): void {
		$this->assertSame( 'pending', PaymentStatus::Pending->value );
		$this->assertSame( 'not_started', FulfilmentStatus::NotStarted->value );
	}

	public function test_all_values_fit_status_columns(): void {
		foreach ( array_merge( PaymentStatus::cases(), FulfilmentStatus::cases() ) as $status ) {
			$this->assertLessThanOrEqual( 20, strlen( $status->value ), $status->value );
		}
	}

	public function test_fulfilment_lifecycle(): void {
		$this->assertTrue( FulfilmentStatus::NotStarted->can_transition_to( FulfilmentStatus::Pending ) );
		$this->assertTrue( FulfilmentStatus::Pending->can_transition_to( FulfilmentStatus::InProgress ) );
		$this->assertTrue( FulfilmentStatus::InProgress->can_transition_to( FulfilmentStatus::Completed ) );
		$this->assertTrue( FulfilmentStatus::InProgress->can_transition_to( FulfilmentStatus::Failed ) );
		$this->assertTrue( FulfilmentStatus::Failed->can_transition_to( FulfilmentStatus::InProgress ) );
		$this->assertTrue( FulfilmentStatus::InProgress->can_transition_to( FulfilmentStatus::FailedPermanent ) );
		$this->assertTrue( FulfilmentStatus::FailedPermanent->can_transition_to( FulfilmentStatus::Pending ) );
	}

	public function test_completed_fulfilment_only_reopens_through_administrator_reset(): void {
		foreach ( FulfilmentStatus::cases() as $status ) {
			if ( FulfilmentStatus::Pending === $status ) {
				continue;
			}
			$this->assertFalse( FulfilmentStatus::Completed->can_transition_to( $status ), $status->value );
		}
		$this->assertTrue( FulfilmentStatus::Completed->can_transition_to( FulfilmentStatus::Pending ) );
	}

	public function test_fulfilment_cannot_start_before_payment_confirmation(): void {
		$this->assertFalse( FulfilmentStatus::NotStarted->can_transition_to( FulfilmentStatus::InProgress ) );
		$this->assertFalse( FulfilmentStatus::NotStarted->can_transition_to( FulfilmentStatus::Completed ) );
	}

	public function test_claimable_states(): void {
		$this->assertSame(
			array( FulfilmentStatus::Pending, FulfilmentStatus::Failed ),
			FulfilmentStatus::InProgress->allowed_from()
		);
	}
}
