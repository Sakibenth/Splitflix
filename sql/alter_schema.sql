USE splitflix;
ALTER TABLE subscription_group DROP FOREIGN KEY subscription_group_ibfk_3;
ALTER TABLE subscription_group DROP COLUMN plan_id;
ALTER TABLE subscription_group ADD COLUMN plan_description VARCHAR(255) NOT NULL AFTER platform_id;
