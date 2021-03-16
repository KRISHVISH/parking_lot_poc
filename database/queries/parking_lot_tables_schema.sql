IF  NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[users]') AND type in (N'U'))
BEGIN

CREATE TABLE [dbo].[users](
	[id] [bigint] IDENTITY(1,1) NOT NULL,
	[name] [nvarchar](255) NOT NULL,
	[email] [nvarchar](255) NOT NULL,
	[password] [nvarchar](255) NOT NULL,
    [status] BIT NOT NULL DEFAULT 1,
	[created_at] [datetime] NULL,
	[updated_at] [datetime] NULL);
END
GO

IF  NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[parking_lot_dashboard]') AND type in (N'U'))
BEGIN

CREATE TABLE dbo.parking_lot_dashboard (
    id [int] IDENTITY NOT NULL PRIMARY KEY,
    total_parking_capacity [int] NOT NULL DEFAULT 0,
    reserved_parking_capacity [int] NOT NULL DEFAULT 0,
    not_reserved_parking_capacity [int] NOT NULL DEFAULT 0
);
END 
GO

IF  NOT EXISTS (SELECT * FROM sys.objects WHERE object_id = OBJECT_ID(N'[dbo].[bookings]') AND type in (N'U'))
BEGIN

CREATE TABLE dbo.bookings (
    id [int] IDENTITY NOT NULL PRIMARY KEY,
    user_id [int] NOT NULL,
    parking_no varchar(250) NOT NULL,
    type varchar(250) NOT NULL DEFAULT('general'),
    status varchar(250) NOT NULL DEFAULT('booked'),
    check_in [DATETIME] NULL,
    check_out [DATETIME] NULL,
    created_at [DATETIME] NULL,
    updated_at [DATETIME] NULL,    
);
END 
GO

-- IF  NOT EXISTS (SELECT * FROM sys.columns WHERE object_id = OBJECT_ID(N'[dbo].[users]') AND name ='other_info')
--  ALTER TABLE dbo.users ADD other_info varchar(max)
-- END