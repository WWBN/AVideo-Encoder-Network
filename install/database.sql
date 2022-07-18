-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- -----------------------------------------------------
-- Schema AVideo-Encoder-Network
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Table `streamers`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `streamers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `siteURL` VARCHAR(255) NOT NULL,
  `user` VARCHAR(45) NOT NULL,
  `pass` VARCHAR(255) NOT NULL,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `encoders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `encoders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `siteURL` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  `streamers_id` INT NOT NULL COMMENT 'Who register it',
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_encoders_streamers1_idx` (`streamers_id` ASC),
  CONSTRAINT `fk_encoders_streamers1`
    FOREIGN KEY (`streamers_id`)
    REFERENCES `streamers` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `scores`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `scores` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `streamers_id` INT NOT NULL,
  `encoders_id` INT NOT NULL,
  `score` INT(1) NOT NULL,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  `comment` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_streamers_has_encoders_encoders1_idx` (`encoders_id` ASC),
  INDEX `fk_streamers_has_encoders_streamers_idx` (`streamers_id` ASC),
  CONSTRAINT `fk_streamers_has_encoders_streamers`
    FOREIGN KEY (`streamers_id`)
    REFERENCES `streamers` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_streamers_has_encoders_encoders1`
    FOREIGN KEY (`encoders_id`)
    REFERENCES `encoders` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
