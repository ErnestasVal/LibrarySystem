DROP TABLE IF EXISTS rezervacija;
DROP TABLE IF EXISTS vartotojas;
DROP TABLE IF EXISTS knyga;

CREATE TABLE knyga
(
    id integer NOT NULL AUTO_INCREMENT,
    pavadinimas varchar(255) NOT NULL,
    autorius varchar(255) NOT NULL,
    puslapiu_sk int NOT NULL,
    egzemplioriu_sk int NOT NULL,
    isdavimo_laikas int NOT NULL,
    PRIMARY KEY(id)
);

CREATE TABLE vartotojas
(
    id integer NOT NULL AUTO_INCREMENT,
    vardas varchar(255) NOT NULL,
    pavarde varchar(255) NOT NULL,
    username varchar(255) NOT NULL,
    password varchar(255) NOT NULL,
    tipas char(16) NOT NULL,
    CHECK(tipas in ('Administratorius', 'Bibliotekininkas', 'Klientas')),
    PRIMARY KEY(id)
);

CREATE TABLE rezervacija
(
    id integer NOT NULL AUTO_INCREMENT,
    issiemimo_data date NOT NULL,
    grazinimo_data date NULL,
    ar_grazinta boolean NOT NULL DEFAULT FALSE,
    fk_vartotojasid integer NOT NULL,
    fk_knygaid integer NOT NULL,
    PRIMARY KEY(id),
    CONSTRAINT rezervuoja FOREIGN KEY(fk_vartotojasid) REFERENCES vartotojas (id),
    CONSTRAINT parenka FOREIGN KEY(fk_knygaid) REFERENCES knyga (id)
);