from flask_sqlalchemy import SQLAlchemy
from datetime import datetime

db = SQLAlchemy()

class Review(db.Model):
    __tablename__ = 'reviews'
    
    id = db.Column(db.Integer, primary_key=True, autoincrement=True)
    user_id = db.Column(db.Integer, nullable=False)
    product_id = db.Column(db.Integer, nullable=False)
    rating = db.Column(db.Integer, nullable=False)
    comment = db.Column(db.Text, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)

    def to_dict(self):
        return {
            'id': self.id,
            'user_id': self.user_id,
            'product_id': self.product_id,
            'rating': self.rating,
            'comment': self.comment,
            'created_at': self.created_at.isoformat() if self.created_at else None
        }
