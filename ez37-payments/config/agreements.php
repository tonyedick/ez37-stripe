<?php
/**
 * Agreement catalogue — edited by a developer.
 *
 * An offering that lists an `agreement` key cannot be paid for until the
 * customer accepts that agreement. The acceptance (key, version, hash of the
 * text, time) is stored with the order.
 *
 * Fields:
 * - version   Change it whenever the wording changes. Old orders keep the version they accepted.
 * - title     Descriptive heading of the side drawer.
 * - label     Text next to the checkbox.
 * - draft     true while the wording has not been approved by EZ37. A notice shows in the drawer.
 * - sections  list of heading + paragraphs. Plain text only (escaped on output).
 *
 * The wording below is the client's Abridged Participant Enrolment Agreement, with the client's
 * Refund Policy and Transfer of Credit Policy added as sections so the customer sees what they
 * agree to ("Agree to the refund policy"). Set `draft` to true to show a notice while wording is being reviewed.
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;

return array(
	'pccp-enrolment' => array(
		'version'  => '2026-11-24',
		'title'    => 'Professional Coach Certification Programme: Enrolment Agreement',
		'label'    => 'I have read and agree to the Enrolment Agreement',
		'draft'    => false,
		'sections' => array(
			array(
				'heading'    => 'Abridged Participant Enrolment Agreement',
				'paragraphs' => array(
					'This PROGRAMME shall provide the necessary training and mentoring hours for ICF Approved Coach Specific Training Hours (ACSTH) as provided on the ICF website.',
				),
			),
			array(
				'heading'    => 'The TRAINING INSTITUTION commits to',
				'paragraphs' => array(
					'Ensuring that all information provided by the PARTICIPANT shall be kept confidential.',
					'1. A relationship with the PARTICIPANT that shall involve requesting information from the PARTICIPANT and making observations about the PARTICIPANT’S coaching strengths and weaknesses including areas the PARTICIPANT needs to expand and deepen core coaching competencies.',
					'2. Ensuring that part of the purpose of the PROGRAMME is to support the PARTICIPANT’S confidence in applying a professional coach’s coaching competencies, and skills and is achieved.',
				),
			),
			array(
				'heading'    => 'PARTICIPANT Responsibilities',
				'paragraphs' => array(
					'A PARTICIPANT shall:',
					'1. Read, understand, and abide by the ICF Code of Ethics during and after the programme (The Gold Standard in Coaching, ICF Code of Ethics, coachingfederation.org).',
					'2. Ensure strict confidentiality of all recording and training materials provided during the programme.',
					'3. Fulfil all the requirements of the programme including attendance and assignments. This includes 60 hours of training and 10 hours of Mentoring.',
					'4. Come with an open mind to the programme and share their experiences and acknowledge other experiences and thought processes.',
					'5. Have a workable internet for effective communication.',
					'6. Agree to the refund policy.',
					'7. Submit transcribed recordings for evaluation and if required these recordings will be shared with ICF for review purposes.',
					'8. Provide the training institution with a 48-hour notice if you will be unable to attend any session. Absence from more than 4 class sessions will lead to a paid re-enrolment of the sessions missed.',
					'9. Have a complete attendance for all mentor coaching sessions (one on one mentor coaching and group mentor coaching). Absence from any of the mentor coaching sessions will lead to paid re-enrolment of the sessions missed.',
					'10. Commit to completing all reflection and post-class activities and log them on the Learning management system (LMS) at least two (2) days before the next training session. Failure to do so 3 consecutive times could lead to suspension from further coach training activities, leading to an undue extension of the coaching programme.',
				),
			),
			array(
				'heading'    => 'RESPONSIBILITIES OF THE TRAINING INSTITUTION',
				'paragraphs' => array(
					'The TRAINING INSTITUTION shall:',
					'1. Strive to provide as much information and support during your coach certification journey.',
					'2. Provide feedback on time and with respect towards you as an individual.',
					'3. Provide ten (10) hours of Mentor Assessment including three (3) hours of individual mentoring.',
					'4. Provide 60 hours of coach-specific training.',
					'5. Mentor and assess participants in practice sessions, evaluating performance and providing formal feedback, as required by ICF.',
					'6. Provide training certificates (accredited by ICF) on completion of the required modules, assignments, research, training hours, payments, and full completion of the Learning Management System activity.',
				),
			),
			array(
				'heading'    => 'Refund Policy',
				'paragraphs' => array(
					'Coach Tuition fees are due prior to commencement of the programme. The company offers flexible payment options. We accept payment in Naira based on the parallel rate equivalent. Fees are exclusive of VAT. The company reserves the right to review fee levels at any time.',
					'Cancellation two weeks before commencement date of the course: A refund of the amount paid within 30 days less the $260 admin fee.',
					'Withdrawal before the end of the module: No refund.',
					'Non attendance: No refund.',
				),
			),
			array(
				'heading'    => 'Transfer of Credit Policy',
				'paragraphs' => array(
					'EZ37 Solutions does not offer a transfer of credit policy for the Professional Coach Certification Programme.',
				),
			),
			array(
				'heading'    => 'Governing law and acceptance',
				'paragraphs' => array(
					'This Agreement shall be governed by the laws of the Federal Republic of Nigeria.',
					'By signing this agreement, participants indicate acceptance of enrolment, understanding of the services offered by the training institution, and agree to accept any changes in programme schedule (date/time) if ever that happens.',
				),
			),
		),
	),
	'accp-enrolment' => array(
		'version'  => '2026-11-24',
		'title'    => 'Advanced Coach Certification Programme: Enrolment Agreement',
		'label'    => 'I have read and agree to the Enrolment Agreement',
		'draft'    => false,
		'sections' => array(
			array(
				'heading'    => 'Abridged Participant Enrolment Agreement',
				'paragraphs' => array(
					'This PROGRAMME shall provide the necessary training and mentoring hours for ICF Approved Coach Specific Training Hours (ACSTH) as provided on the ICF website.',
				),
			),
			array(
				'heading'    => 'The TRAINING INSTITUTION commits to',
				'paragraphs' => array(
					'Ensuring that all information provided by the PARTICIPANT shall be kept confidential.',
					'1. A relationship with the PARTICIPANT that shall involve requesting information from the PARTICIPANT and making observations about the PARTICIPANT’S coaching strengths and weaknesses including areas the PARTICIPANT needs to expand and deepen core coaching competencies.',
					'2. Ensuring that part of the purpose of the PROGRAMME is to support the PARTICIPANT’S confidence in applying a professional coach’s coaching competencies, and skills and is achieved.',
				),
			),
			array(
				'heading'    => 'PARTICIPANT Responsibilities',
				'paragraphs' => array(
					'A PARTICIPANT shall:',
					'1. Read, understand, and abide by the ICF Code of Ethics during and after the programme (The Gold Standard in Coaching, ICF Code of Ethics, coachingfederation.org).',
					'2. Ensure strict confidentiality of all recording and training materials provided during the programme.',
					'3. Fulfil all the requirements of the programme including attendance and assignments. This includes 60 hours of training and 10 hours of Mentoring.',
					'4. Come with an open mind to the programme and share their experiences and acknowledge other experiences and thought processes.',
					'5. Have a workable internet for effective communication.',
					'6. Agree to the refund policy.',
					'7. Submit transcribed recordings for evaluation and if required these recordings will be shared with ICF for review purposes.',
					'8. Provide the training institution with a 48-hour notice if you will be unable to attend any session. Absence from more than 4 class sessions will lead to a paid re-enrolment of the sessions missed.',
					'9. Have a complete attendance for all mentor coaching sessions (one on one mentor coaching and group mentor coaching). Absence from any of the mentor coaching sessions will lead to paid re-enrolment of the sessions missed.',
					'10. Commit to completing all reflection and post-class activities and log them on the Learning management system (LMS) at least two (2) days before the next training session. Failure to do so 3 consecutive times could lead to suspension from further coach training activities, leading to an undue extension of the coaching programme.',
				),
			),
			array(
				'heading'    => 'RESPONSIBILITIES OF THE TRAINING INSTITUTION',
				'paragraphs' => array(
					'The TRAINING INSTITUTION shall:',
					'1. Strive to provide as much information and support during your coach certification journey.',
					'2. Provide feedback on time and with respect towards you as an individual.',
					'3. Provide ten (10) hours of Mentor Assessment including three (3) hours of individual mentoring.',
					'4. Provide 60 hours of coach-specific training.',
					'5. Mentor and assess participants in practice sessions, evaluating performance and providing formal feedback, as required by ICF.',
					'6. Provide training certificates (accredited by ICF) on completion of the required modules, assignments, research, training hours, payments, and full completion of the Learning Management System activity.',
				),
			),
			array(
				'heading'    => 'Refund Policy',
				'paragraphs' => array(
					'Coach Tuition fees are due prior to commencement of the programme. The company offers flexible payment options. We accept payment in Naira based on the parallel rate equivalent. Fees are exclusive of VAT. The company reserves the right to review fee levels at any time.',
					'Cancellation two weeks before commencement date of the course: A refund of the amount paid within 30 days less the $260 admin fee.',
					'Withdrawal before the end of the module: No refund.',
					'Non attendance: No refund.',
				),
			),
			array(
				'heading'    => 'Transfer of Credit Policy',
				'paragraphs' => array(
					'EZ37 Solutions does not offer a transfer of credit policy for the Advanced Coach Certification Programme.',
				),
			),
			array(
				'heading'    => 'Governing law and acceptance',
				'paragraphs' => array(
					'This Agreement shall be governed by the laws of the Federal Republic of Nigeria.',
					'By signing this agreement, participants indicate acceptance of enrolment, understanding of the services offered by the training institution, and agree to accept any changes in programme schedule (date/time) if ever that happens.',
				),
			),
		),
	),
	'peer-to-peer-agreement' => array(
		'version'  => '2026-11-25',
		'title'    => 'EZ Peer to Peer Coaching: Agreement',
		'label'    => 'I acknowledge that I have read and accept the terms and conditions of this Agreement',
		'draft'    => false,
		'sections' => array(
			array(
				'heading'    => 'Peer to Peer Agreement',
				'paragraphs' => array(
					'I will:',
					'1. Arrive on time for coaching sessions.',
					'2. Take part fully and with an open mind.',
					'3. Use the ICF core competencies in coaching.',
					'4. Let the peer coach know at the start of the coaching session if I need to record the session as part of a performance evaluation.',
					'5. Be eligible to receive promotional literature from EZ37 Solutions on learning and development programmes.',
					'6. Release EZ37 Solutions and EZ Coaching from any and all liability arising from coaching sessions where I am a coach or where I am being coached.',
				),
			),
		),
	),
);
