USE splitflix;
ALTER TABLE subscription_group ADD COLUMN payment_form_link VARCHAR(255) DEFAULT NULL;
