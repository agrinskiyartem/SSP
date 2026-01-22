CREATE DATABASE IF NOT EXISTS atm_coursework
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE atm_coursework;

-- Таблицы
CREATE TABLE banks (
  bank_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','operator') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE atms (
  atm_id INT AUTO_INCREMENT PRIMARY KEY,
  bank_id INT NOT NULL,
  location VARCHAR(255) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_atms_bank
    FOREIGN KEY (bank_id) REFERENCES banks(bank_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_atms_bank_id (bank_id)
) ENGINE=InnoDB;

CREATE TABLE customers (
  customer_id INT AUTO_INCREMENT PRIMARY KEY,
  bank_id INT NOT NULL,
  full_name VARCHAR(200) NOT NULL,
  CONSTRAINT fk_customers_bank
    FOREIGN KEY (bank_id) REFERENCES banks(bank_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_customers_bank_id (bank_id)
) ENGINE=InnoDB;

CREATE TABLE accounts (
  account_id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  currency CHAR(3) NOT NULL DEFAULT 'RUB',
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_accounts_customer
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_accounts_customer_id (customer_id)
) ENGINE=InnoDB;

CREATE TABLE cards (
  card_id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  issuing_bank_id INT NOT NULL,
  pan_last4 CHAR(4) NOT NULL,
  exp_date DATE NOT NULL,
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_cards_account
    FOREIGN KEY (account_id) REFERENCES accounts(account_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_cards_issuing_bank
    FOREIGN KEY (issuing_bank_id) REFERENCES banks(bank_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY uq_cards_account_last4 (account_id, pan_last4),
  INDEX idx_cards_issuing_bank_id (issuing_bank_id)
) ENGINE=InnoDB;

CREATE TABLE withdrawals (
  withdrawal_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  atm_id INT NOT NULL,
  card_id INT NOT NULL,
  account_id INT NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  commission_amount DECIMAL(14,2) NOT NULL,
  total_debit DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_withdrawals_atm
    FOREIGN KEY (atm_id) REFERENCES atms(atm_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_withdrawals_card
    FOREIGN KEY (card_id) REFERENCES cards(card_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_withdrawals_account
    FOREIGN KEY (account_id) REFERENCES accounts(account_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_withdrawals_atm_id (atm_id),
  INDEX idx_withdrawals_card_id (card_id),
  INDEX idx_withdrawals_created_at (created_at)
) ENGINE=InnoDB;

-- Триггер: расчёт комиссии и общего списания
DELIMITER $$
CREATE TRIGGER trg_withdrawals_calc_commission
BEFORE INSERT ON withdrawals
FOR EACH ROW
BEGIN
  DECLARE atm_bank_id INT;
  DECLARE card_issuing_bank_id INT;
  DECLARE commission_rate DECIMAL(6,4);

  SELECT bank_id INTO atm_bank_id
  FROM atms WHERE atm_id = NEW.atm_id;

  SELECT issuing_bank_id INTO card_issuing_bank_id
  FROM cards WHERE card_id = NEW.card_id;

  IF atm_bank_id = card_issuing_bank_id THEN
    SET commission_rate = 0.0000;
  ELSE
    SET commission_rate = 0.0120;
  END IF;

  SET NEW.commission_amount = ROUND(NEW.amount * commission_rate, 2);
  SET NEW.total_debit = NEW.amount + NEW.commission_amount;
END$$
DELIMITER ;

-- Процедура: попытка снять наличные (с контролем баланса)
DELIMITER $$
CREATE PROCEDURE sp_withdraw_cash (
  IN p_atm_id INT,
  IN p_card_id INT,
  IN p_amount DECIMAL(14,2)
)
BEGIN
  DECLARE v_account_id INT;
  DECLARE v_balance DECIMAL(14,2);
  DECLARE v_commission DECIMAL(14,2);
  DECLARE v_total DECIMAL(14,2);

  START TRANSACTION;

  SELECT a.account_id, a.balance
  INTO v_account_id, v_balance
  FROM accounts a
  JOIN cards c ON c.account_id = a.account_id
  WHERE c.card_id = p_card_id
  FOR UPDATE;

  INSERT INTO withdrawals (atm_id, card_id, account_id, amount, commission_amount, total_debit)
  VALUES (p_atm_id, p_card_id, v_account_id, p_amount, 0.00, 0.00);

  SELECT commission_amount, total_debit
  INTO v_commission, v_total
  FROM withdrawals
  WHERE withdrawal_id = LAST_INSERT_ID();

  IF v_balance < v_total THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Недостаточно средств';
  ELSE
    UPDATE accounts
    SET balance = balance - v_total
    WHERE account_id = v_account_id;

    COMMIT;
  END IF;
END$$
DELIMITER ;

-- Начальные данные
INSERT INTO banks (name) VALUES
  ('Банк Северный'),
  ('Банк Восточный'),
  ('Банк Центральный');

INSERT INTO atms (bank_id, location, status) VALUES
  (1, 'Москва, Тверская 10', 'active'),
  (1, 'Москва, Ленинградский проспект 5', 'active'),
  (2, 'Казань, Кремлевская 3', 'active'),
  (3, 'Санкт-Петербург, Невский 100', 'active');

INSERT INTO customers (bank_id, full_name) VALUES
  (1, 'Иванов Алексей'),
  (1, 'Петрова Марина'),
  (2, 'Сидоров Павел'),
  (3, 'Кузнецова Ольга');

INSERT INTO accounts (customer_id, balance, currency, status) VALUES
  (1, 50000.00, 'RUB', 'active'),
  (2, 32000.00, 'RUB', 'active'),
  (3, 15000.00, 'RUB', 'active'),
  (4, 82000.00, 'RUB', 'active');

INSERT INTO cards (account_id, issuing_bank_id, pan_last4, exp_date, status) VALUES
  (1, 1, '1234', '2027-12-31', 'active'),
  (2, 1, '5678', '2026-10-31', 'active'),
  (3, 2, '4321', '2028-01-31', 'active'),
  (4, 3, '8765', '2026-07-31', 'active');

INSERT INTO withdrawals (atm_id, card_id, account_id, amount, commission_amount, total_debit, created_at) VALUES
  (1, 1, 1, 5000.00, 0.00, 5000.00, NOW()),
  (3, 1, 1, 2000.00, 0.00, 2000.00, NOW()),
  (2, 3, 3, 1000.00, 0.00, 1000.00, NOW()),
  (4, 4, 4, 7000.00, 0.00, 7000.00, NOW());

INSERT INTO users (username, password_hash, role) VALUES
  ('admin', '$2y$12$EQHqpEjLeSggi4SJmUBRS.PJV8YZE/GUWRUdwnICCTXFT6drAGIrG', 'admin'),
  ('operator', '$2y$12$aAFRjfxbgskCWrE1eid.DufbQdII8urUkOE8Df297OjxmTWawRyHK', 'operator');
