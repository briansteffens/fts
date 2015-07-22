create table files
(
    id char(10)
,   file_size bigint
,   chunk_size int
,   file_hash char(64)
,   date_started datetime
,   date_created datetime
,   file_name varchar(255)
,   content_type varchar(255)

,   primary key (id)
);

create table chunks
(
    file_id char(10)
,   chunk_index int
,   chunk_hash char(64)

,   primary key (file_id, chunk_index)

,   foreign key (file_id) references files(id)
);