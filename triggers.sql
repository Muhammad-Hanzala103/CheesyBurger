DELIMITER ;;

DROP TRIGGER IF EXISTS trg_order_status_change;;
CREATE TRIGGER trg_order_status_change
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
  IF NEW.status != OLD.status THEN
    INSERT INTO order_status_log (order_id, status, changed_at, changed_by)
    VALUES (NEW.id, NEW.status, NOW(), @changed_by);
  END IF;
END;;

DROP TRIGGER IF EXISTS trg_order_status_insert;;
CREATE TRIGGER trg_order_status_insert
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
  INSERT INTO order_status_log (order_id, status, changed_at, changed_by)
  VALUES (NEW.id, NEW.status, NOW(), @changed_by);
END;;

DELIMITER ;