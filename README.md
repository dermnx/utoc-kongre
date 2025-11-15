# utoc-kongre

## Veritabanı Notu

Admin panelinden manuel ödeme onayı verebilmek için `kongre_registrations` tablosuna yeni bir `payment_confirmed` (TINYINT/BOOLEAN) sütunu eklenmelidir. Önerilen SQL:

```sql
ALTER TABLE kongre_registrations
    ADD COLUMN payment_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_amount;
```

Var olan kayıtlar otomatik olarak beklemede (`0`) kalır; daha önce ödeme aldığı bilinen kayıtları isteğe göre `1` ile güncelleyebilirsiniz.
