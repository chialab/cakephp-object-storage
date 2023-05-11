create table files
(
    id              char(36)     not null       primary key,
    filename        varchar(255) not null,
    mime_type       varchar(255) null,
    size            int unsigned not null,
    multipart_token varchar(255) null,
    created         datetime     not null,
    finalized       datetime     null
);

create index file_created_idx
    on files (created);

create index file_filename_idx
    on files (filename);

create index file_finalized_idx
    on files (finalized);

create index file_mime_type_idx
    on files (mime_type);

create index file_size_idx
    on files (size);
