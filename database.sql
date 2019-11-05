CREATE TABLE IF NOT EXISTS `vsbridge_token` (
  `vsbridge_token_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `token` varchar(32) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `timestamp` int(11) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
ALTER TABLE
  `vsbridge_token`
ADD
  PRIMARY KEY (`vsbridge_token_id`),
ADD
  UNIQUE KEY `token` (`token`);
ALTER TABLE
  `vsbridge_token` MODIFY `vsbridge_token_id` int(11) NOT NULL AUTO_INCREMENT;
CREATE TABLE IF NOT EXISTS `vsbridge_refresh_token` (
  `vsbridge_refresh_token_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `timestamp` int(11) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
ALTER TABLE
  `vsbridge_refresh_token`
ADD
  PRIMARY KEY (`vsbridge_refresh_token_id`);
ALTER TABLE
  `vsbridge_refresh_token` MODIFY `vsbridge_refresh_token_id` int(11) NOT NULL AUTO_INCREMENT;
CREATE TABLE IF NOT EXISTS `vsbridge_session` (
  `customer_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `session_id` varchar(32) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
ALTER TABLE
  `vsbridge_session`
ADD
  UNIQUE `unique_index`(`customer_id`, `store_id`);
