<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T-02 - Initial migration (ADR-001: the schema belongs to the application migrations).
 *
 * Mirrors data-model.md exactly:
 *  - §3: six singular tables in schema "library", same column order and types, no DEFAULT, no created_at/updated_at.
 *  - §4: only the rules marked "motor". ck_book_total_positive and ck_user_role are "pendiente (T-18)" and are NOT created here.
 *  - §5: FK ON DELETE policy; loan_item.category_name has no FK on purpose (ADR-004).
 *  - §9: the five categories with their fixed ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE SCHEMA library;

            CREATE TABLE library.category (
                id   uuid         NOT NULL PRIMARY KEY,
                name varchar(120) NOT NULL
            );
            CREATE UNIQUE INDEX ux_category_name ON library.category (name);

            CREATE TABLE library.book (
                id               uuid         NOT NULL PRIMARY KEY,
                title            varchar(250) NOT NULL,
                author           varchar(200) NOT NULL,
                isbn             char(13)     NOT NULL,
                category_id      uuid         NOT NULL,
                total_copies     integer      NOT NULL,
                available_copies integer      NOT NULL,
                cover_key        varchar(512) NULL,
                deleted_at       timestamptz  NULL,
                CONSTRAINT fk_book_category_id FOREIGN KEY (category_id)
                    REFERENCES library.category (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT ck_book_available_range
                    CHECK (available_copies >= 0 AND available_copies <= total_copies)
            );
            CREATE UNIQUE INDEX ux_book_isbn_active ON library.book (isbn) WHERE deleted_at IS NULL;
            CREATE INDEX ix_book_category_id_title_active ON library.book (category_id, title) WHERE deleted_at IS NULL;

            CREATE TABLE library.member (
                id              uuid         NOT NULL PRIMARY KEY,
                full_name       varchar(200) NOT NULL,
                document_number varchar(30)  NOT NULL
            );
            CREATE UNIQUE INDEX ux_member_document_number ON library.member (document_number);

            CREATE TABLE library."user" (
                id            uuid         NOT NULL PRIMARY KEY,
                username      varchar(120) NOT NULL,
                password_hash varchar(512) NOT NULL,
                role          varchar(40)  NOT NULL
            );
            CREATE UNIQUE INDEX ux_user_username ON library."user" (username);

            CREATE TABLE library.loan (
                id                uuid        NOT NULL PRIMARY KEY,
                member_id         uuid        NOT NULL,
                loaned_by_user_id uuid        NOT NULL,
                loaned_at         timestamptz NOT NULL,
                CONSTRAINT fk_loan_member_id FOREIGN KEY (member_id)
                    REFERENCES library.member (id) ON DELETE RESTRICT ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_loaned_by_user_id FOREIGN KEY (loaned_by_user_id)
                    REFERENCES library."user" (id) ON DELETE RESTRICT ON UPDATE NO ACTION
            );
            CREATE INDEX ix_loan_loaned_at ON library.loan (loaned_at);

            CREATE TABLE library.loan_item (
                id            uuid         NOT NULL PRIMARY KEY,
                loan_id       uuid         NOT NULL,
                book_id       uuid         NOT NULL,
                book_title    varchar(250) NOT NULL,
                isbn          char(13)     NOT NULL,
                category_name varchar(120) NOT NULL,
                due_at        timestamptz  NOT NULL,
                returned_at   timestamptz  NULL,
                CONSTRAINT fk_loan_item_loan_id FOREIGN KEY (loan_id)
                    REFERENCES library.loan (id) ON DELETE CASCADE ON UPDATE NO ACTION,
                CONSTRAINT fk_loan_item_book_id FOREIGN KEY (book_id)
                    REFERENCES library.book (id) ON DELETE RESTRICT ON UPDATE NO ACTION
            );
            CREATE UNIQUE INDEX ux_loan_item_loan_id_book_id ON library.loan_item (loan_id, book_id);
            CREATE INDEX ix_loan_item_book_id ON library.loan_item (book_id);
            CREATE INDEX ix_loan_item_due_at_open ON library.loan_item (due_at) WHERE returned_at IS NULL;

            INSERT INTO library.category (id, name) VALUES
                ('11111111-1111-4111-8111-111111111111', 'Novela'),
                ('22222222-2222-4222-8222-222222222222', 'Ciencia'),
                ('33333333-3333-4333-8333-333333333333', 'Tecnología'),
                ('44444444-4444-4444-8444-444444444444', 'Historia'),
                ('55555555-5555-4555-8555-555555555555', 'Infantil');
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP SCHEMA library CASCADE;');
    }
};
